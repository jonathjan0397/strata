<?php

namespace App\Services\Registrars;

use App\Contracts\RegistrarDriver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Enom XML API driver.
 * Docs: https://www.enom.com/APICommandCatalog/
 */
class EnomDriver implements RegistrarDriver
{
    private string $uid;

    private string $pw;

    private string $baseUrl;

    public function __construct()
    {
        $sandbox = config('registrars.enom.sandbox', true);

        $this->uid = config('registrars.enom.uid');
        $this->pw = config('registrars.enom.pw');
        $this->baseUrl = $sandbox
            ? 'https://resellertest.enom.com/interface.asp'
            : 'https://reseller.enom.com/interface.asp';
    }

    public function slug(): string
    {
        return 'enom';
    }

    public function checkAvailability(string $domain): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $data = $this->call('Check', ['SLD' => $sld, 'TLD' => $tld]);

        $available = isset($data['RRPCode']) && (string) $data['RRPCode'] === '210';

        return ['available' => $available];
    }

    public function registerDomain(string $domain, int $years, array $contact): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $nameservers = $contact['nameservers'] ?? [];

        $params = array_merge(
            [
                'SLD' => $sld,
                'TLD' => $tld,
                'NumYears' => $years,
                'UseDNS' => empty($nameservers) ? 'default' : 'custom',
            ],
            $this->buildContactParams('Registrant', $contact),
            $this->buildContactParams('Tech', $contact),
            $this->buildContactParams('Admin', $contact),
            $this->buildContactParams('AuxBilling', $contact),
        );

        foreach (array_values($nameservers) as $i => $ns) {
            $params['NS'.($i + 1)] = $ns;
        }

        $data = $this->call('Purchase', $params);

        if (! isset($data['RRPCode']) || (string) $data['RRPCode'] !== '200') {
            $msg = $data['RRPText'] ?? 'Unknown error';
            throw new RuntimeException("Enom registration failed: {$msg}");
        }

        return [
            'success' => true,
            'registrar_data' => [
                'order_id' => $data['OrderID'] ?? null,
            ],
        ];
    }

    public function renewDomain(string $domain, int $years): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $data = $this->call('Extend', [
            'SLD' => $sld,
            'TLD' => $tld,
            'NumYears' => $years,
        ]);

        if (! isset($data['RRPCode']) || (string) $data['RRPCode'] !== '200') {
            throw new RuntimeException('Enom renewal failed: '.($data['RRPText'] ?? 'Unknown'));
        }

        return [
            'success' => true,
            'expires_at' => $data['ExpirationDate'] ?? '',
        ];
    }

    public function transferDomain(string $domain, string $authCode): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $data = $this->call('TP_CreateOrder', [
            'SLD' => $sld,
            'TLD' => $tld,
            'AuthInfo' => $authCode,
            'NumYears' => 1,
        ]);

        return [
            'success' => true,
            'transfer_id' => $data['TransferOrderDetailID'] ?? '',
        ];
    }

    public function getNameservers(string $domain): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $data = $this->call('GetDNS', ['SLD' => $sld, 'TLD' => $tld]);

        $ns = [];
        for ($i = 1; $i <= 13; $i++) {
            $key = "NS{$i}";
            if (! empty($data[$key])) {
                $ns[] = $data[$key];
            }
        }

        return $ns;
    }

    public function setNameservers(string $domain, array $nameservers): void
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $params = ['SLD' => $sld, 'TLD' => $tld];
        foreach (array_values($nameservers) as $i => $ns) {
            $params['NS'.($i + 1)] = $ns;
        }

        $this->call('ModifyNS', $params);
    }

    public function getInfo(string $domain): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $data = $this->call('GetDomainInfo', ['SLD' => $sld, 'TLD' => $tld]);

        $ns = [];
        for ($i = 1; $i <= 13; $i++) {
            $key = "NS{$i}";
            if (! empty($data['services'][$key])) {
                $ns[] = $data['services'][$key];
            }
        }

        return [
            'expires_at' => $data['expiration'] ?? '',
            'locked' => isset($data['registrarlock']) && $data['registrarlock'] === '1',
            'privacy' => false, // Enom uses separate WhoIsGuard product
            'nameservers' => $ns,
        ];
    }

    public function setLock(string $domain, bool $locked): void
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $this->call('SetRegLock', [
            'SLD' => $sld,
            'TLD' => $tld,
            'UnlockRegistrar' => $locked ? '0' : '1',
        ]);
    }

    public function setPrivacy(string $domain, bool $enabled): void
    {
        // Enom WhoIsGuard is a separate product; no-op here.
        // Operators can manage this through the Enom reseller panel.
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function call(string $command, array $params = []): array
    {
        $response = Http::get($this->baseUrl, array_merge([
            'uid' => $this->uid,
            'pw' => $this->pw,
            'command' => $command,
            'responsetype' => 'xml',
        ], $params));

        $xml = simplexml_load_string($response->body());
        if ($xml === false) {
            throw new RuntimeException("Enom API returned unparseable response for [{$command}].");
        }

        // Convert to associative array (flatten top level)
        $data = [];
        foreach ($xml as $key => $value) {
            $data[strtolower((string) $key)] = (string) $value;
        }

        // Also keep original cased keys
        foreach ($xml as $key => $value) {
            $data[(string) $key] = (string) $value;
        }

        if (isset($data['ErrCount']) && (int) $data['ErrCount'] > 0) {
            $err = $data['Err1'] ?? 'Unknown Enom error';
            throw new RuntimeException("Enom API error [{$command}]: {$err}");
        }

        return $data;
    }

    private function splitDomain(string $domain): array
    {
        $parts = explode('.', $domain, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function buildContactParams(string $type, array $c): array
    {
        return [
            "{$type}FirstName" => $c['registrant_first'],
            "{$type}LastName" => $c['registrant_last'],
            "{$type}Email" => $c['registrant_email'],
            "{$type}Phone" => $c['registrant_phone'],
            "{$type}Address1" => $c['registrant_address'],
            "{$type}City" => $c['registrant_city'],
            "{$type}StateProvinceChoice" => 'S',
            "{$type}StateProvince" => $c['registrant_state'],
            "{$type}PostalCode" => $c['registrant_zip'],
            "{$type}Country" => $c['registrant_country'],
        ];
    }

    /**
     * Fetch reseller cost pricing for all TLDs.
     * Uses PE_GetProductList (eNom extended product catalog API).
     *
     * @return array<string, array{register: float|null, renew: float|null, transfer: float|null, currency: string}>
     */
    public function getPricing(): array
    {
        $pricing = [];

        $actions = [
            'REGISTER' => 'register',
            'RENEW' => 'renew',
            'TRANSFER' => 'transfer',
        ];

        foreach ($actions as $enomAction => $key) {
            try {
                $raw = Http::get($this->baseUrl, [
                    'uid' => $this->uid,
                    'pw' => $this->pw,
                    'command' => 'PE_GetProductList',
                    'responsetype' => 'xml',
                    'ProductType' => 'Domain',
                    'ProductCategory' => strtoupper($enomAction),
                ]);

                $xml = simplexml_load_string($raw->body());
                if ($xml === false) {
                    continue;
                }

                foreach ($xml->ProductList->Product ?? [] as $product) {
                    $tld = strtolower(ltrim((string) $product->TLD, '.'));
                    $price = isset($product->Price) ? (float) $product->Price : null;

                    if ($tld === '') {
                        continue;
                    }

                    $pricing[$tld][$key] = $price;
                    $pricing[$tld]['currency'] = 'USD';
                }
            } catch (\Throwable) {
                // partial failure — continue with other actions
            }
        }

        return $pricing;
    }
}
