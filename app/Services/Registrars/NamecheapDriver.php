<?php

namespace App\Services\Registrars;

use App\Contracts\RegistrarDriver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NamecheapDriver implements RegistrarDriver
{
    private string $apiUser;
    private string $apiKey;
    private string $clientIp;
    private string $baseUrl;

    public function __construct()
    {
        $sandbox = config('registrars.namecheap.sandbox', true);

        $this->apiUser  = config('registrars.namecheap.api_user');
        $this->apiKey   = config('registrars.namecheap.api_key');
        $this->clientIp = config('registrars.namecheap.client_ip', '127.0.0.1');
        $this->baseUrl  = $sandbox
            ? 'https://api.sandbox.namecheap.com/xml.response'
            : 'https://api.namecheap.com/xml.response';
    }

    public function slug(): string
    {
        return 'namecheap';
    }

    public function checkAvailability(string $domain): array
    {
        $xml = $this->call('namecheap.domains.check', ['DomainList' => $domain]);

        $result = $xml->CommandResponse->DomainCheckResult;
        $available = strtolower((string) $result['Available']) === 'true';

        return ['available' => $available];
    }

    public function registerDomain(string $domain, int $years, array $contact): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $nameservers = $contact['nameservers'] ?? [];

        $params = array_merge(
            [
                'DomainName' => $domain,
                'Years'      => $years,
            ],
            $this->buildContactParams('Registrant', $contact),
            $this->buildContactParams('Tech', $contact),
            $this->buildContactParams('Admin', $contact),
            $this->buildContactParams('AuxBilling', $contact),
        );

        if (! empty($nameservers)) {
            $params['Nameservers'] = implode(',', $nameservers);
        }

        $xml = $this->call('namecheap.domains.create', $params);

        $result = $xml->CommandResponse->DomainCreateResult;
        if ((string) $result['Registered'] !== 'true') {
            throw new RuntimeException('Namecheap domain registration failed.');
        }

        return [
            'success'       => true,
            'registrar_data' => [
                'domain_id'   => (string) $result['DomainID'],
                'order_id'    => (string) $result['OrderID'],
                'transaction' => (string) $result['TransactionID'],
            ],
        ];
    }

    public function renewDomain(string $domain, int $years): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $xml = $this->call('namecheap.domains.renew', [
            'DomainName' => $domain,
            'Years'      => $years,
        ]);

        $result = $xml->CommandResponse->DomainRenewResult;
        if ((string) $result['Renew'] !== 'true') {
            throw new RuntimeException('Namecheap domain renewal failed.');
        }

        return [
            'success'    => true,
            'expires_at' => (string) $result['DomainDetails']['ExpiredDate'],
        ];
    }

    public function transferDomain(string $domain, string $authCode): array
    {
        $xml = $this->call('namecheap.domains.transfer.create', [
            'DomainName' => $domain,
            'EPPCode'    => $authCode,
            'Years'      => 1,
        ]);

        $result = $xml->CommandResponse->DomainTransferCreateResult;

        return [
            'success'     => true,
            'transfer_id' => (string) $result['TransferID'],
        ];
    }

    public function getNameservers(string $domain): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $xml = $this->call('namecheap.domains.dns.getList', [
            'SLD' => $sld,
            'TLD' => $tld,
        ]);

        $ns = [];
        foreach ($xml->CommandResponse->DomainDNSGetListResult->Nameserver as $n) {
            $ns[] = (string) $n;
        }

        return $ns;
    }

    public function setNameservers(string $domain, array $nameservers): void
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $this->call('namecheap.domains.dns.setCustom', [
            'SLD'         => $sld,
            'TLD'         => $tld,
            'Nameservers' => implode(',', $nameservers),
        ]);
    }

    public function getInfo(string $domain): array
    {
        $xml = $this->call('namecheap.domains.getInfo', ['DomainName' => $domain]);

        $info = $xml->CommandResponse->DomainGetInfoResult;
        $details = $info->DomainDetails;
        $modifs  = $info->Modificationrights;
        $ns      = [];

        foreach ($info->DnsDetails->Nameserver as $n) {
            $ns[] = (string) $n;
        }

        return [
            'expires_at'  => (string) $details->ExpiredDate,
            'locked'      => strtolower((string) $info['IsLocked']) === 'true',
            'privacy'     => strtolower((string) $info->Whoisguard['Enabled']) === 'true',
            'nameservers' => $ns,
        ];
    }

    public function setLock(string $domain, bool $locked): void
    {
        $this->call('namecheap.domains.setRegistrarLock', [
            'DomainName' => $domain,
            'LockAction' => $locked ? 'LOCK' : 'UNLOCK',
        ]);
    }

    public function setPrivacy(string $domain, bool $enabled): void
    {
        $this->call('namecheap.whoisguard.enable', [
            'DomainName'    => $domain,
            'ForwardedToMail' => '',
        ]);

        if (! $enabled) {
            $this->call('namecheap.whoisguard.disable', ['DomainName' => $domain]);
        }
    }

    /**
     * Fetch registrar cost pricing for all TLDs via namecheap.users.getPricing.
     *
     * @return array<string, array{register: float|null, renew: float|null, transfer: float|null, currency: string}>
     */
    public function getPricing(): array
    {
        $pricing = [];

        foreach (['REGISTER' => 'register', 'RENEW' => 'renew', 'TRANSFER' => 'transfer'] as $action => $key) {
            try {
                $xml = $this->call('namecheap.users.getPricing', [
                    'ProductType' => 'DOMAIN',
                    'ActionName'  => $action,
                ]);

                foreach ($xml->CommandResponse->UserGetPricingResult->ProductType ?? [] as $productType) {
                    foreach ($productType->ProductCategory as $category) {
                        foreach ($category->Product as $product) {
                            $tld  = strtolower((string) $product['Name']);
                            $price = null;
                            foreach ($product->Price as $p) {
                                if ((int) $p['Duration'] === 1 && (string) $p['DurationType'] === 'YEAR') {
                                    $price = (float) $p['YourPrice'];
                                    break;
                                }
                            }
                            $pricing[$tld][$key]       = $price;
                            $pricing[$tld]['currency'] = 'USD';
                        }
                    }
                }
            } catch (\Throwable) {
                // partial failure — continue with other actions
            }
        }

        return $pricing;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function call(string $command, array $params = []): \SimpleXMLElement
    {
        $response = Http::get($this->baseUrl, array_merge([
            'ApiUser'   => $this->apiUser,
            'ApiKey'    => $this->apiKey,
            'UserName'  => $this->apiUser,
            'Command'   => $command,
            'ClientIp'  => $this->clientIp,
        ], $params));

        $xml = simplexml_load_string($response->body());

        if ((string) $xml['Status'] !== 'OK') {
            $error = (string) ($xml->Errors->Error ?? 'Unknown Namecheap error');
            throw new RuntimeException("Namecheap API error [{$command}]: {$error}");
        }

        return $xml;
    }

    private function splitDomain(string $domain): array
    {
        $parts = explode('.', $domain, 2);
        return [$parts[0], $parts[1] ?? ''];
    }

    private function buildContactParams(string $type, array $c): array
    {
        return [
            "{$type}FirstName"   => $c['registrant_first'],
            "{$type}LastName"    => $c['registrant_last'],
            "{$type}EmailAddress"=> $c['registrant_email'],
            "{$type}Phone"       => $c['registrant_phone'],
            "{$type}Address1"    => $c['registrant_address'],
            "{$type}City"        => $c['registrant_city'],
            "{$type}StateProvince"=> $c['registrant_state'],
            "{$type}PostalCode"  => $c['registrant_zip'],
            "{$type}Country"     => $c['registrant_country'],
        ];
    }
}
