<?php

namespace App\Services\Registrars;

use App\Contracts\RegistrarDriver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenSRS XCP API driver.
 * Docs: https://opensrs.com/docs/api/
 * Authentication: MD5 hash of (body + MD5(apikey))
 */
class OpenSRSDriver implements RegistrarDriver
{
    private string $apiKey;

    private string $reseller;

    private string $baseUrl;

    public function __construct()
    {
        $sandbox = config('registrars.opensrs.sandbox', true);

        $this->apiKey = config('registrars.opensrs.api_key');
        $this->reseller = config('registrars.opensrs.reseller_username');
        $this->baseUrl = $sandbox
            ? 'https://horizon.opensrs.net:55443'
            : 'https://rr-n1-tor.opensrs.net:55443';
    }

    public function slug(): string
    {
        return 'opensrs';
    }

    public function checkAvailability(string $domain): array
    {
        $result = $this->call('lookup', 'domain', ['domain' => $domain]);

        $available = strtolower($result['attributes']['status'] ?? '') === 'available';

        return ['available' => $available];
    }

    public function registerDomain(string $domain, int $years, array $contact): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        $nameservers = $contact['nameservers'] ?? [];
        $nsList = [];
        foreach (array_values($nameservers) as $i => $ns) {
            $nsList["sortorder{$i}"] = $ns;
            $nsList["name{$i}"] = $ns;
        }

        $attributes = [
            'domain' => $domain,
            'period' => $years,
            'reg_username' => $contact['registrant_email'],
            'reg_password' => substr(md5(uniqid()), 0, 12),
            'contact_set' => [
                'owner' => $this->buildContact($contact),
                'admin' => $this->buildContact($contact),
                'billing' => $this->buildContact($contact),
                'tech' => $this->buildContact($contact),
            ],
            'nameserver_list' => array_values(array_map(fn ($ns) => ['name' => $ns, 'sortorder' => 0], $nameservers)),
            'reg_type' => 'new',
        ];

        $result = $this->call('sw_register', 'domain', $attributes);

        $code = (int) ($result['response_code'] ?? 0);
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('OpenSRS registration failed: '.($result['response_text'] ?? 'Unknown'));
        }

        return [
            'success' => true,
            'registrar_data' => [
                'order_id' => $result['attributes']['id'] ?? null,
                'order_id_n' => $result['attributes']['order_id'] ?? null,
            ],
        ];
    }

    public function renewDomain(string $domain, int $years): array
    {
        $info = $this->getInfo($domain);

        $result = $this->call('renew', 'domain', [
            'domain' => $domain,
            'period' => $years,
            'currentexpirationyear' => substr($info['expires_at'], 0, 4),
        ]);

        $code = (int) ($result['response_code'] ?? 0);
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('OpenSRS renewal failed: '.($result['response_text'] ?? 'Unknown'));
        }

        return [
            'success' => true,
            'expires_at' => $result['attributes']['expiration_date'] ?? '',
        ];
    }

    public function transferDomain(string $domain, string $authCode): array
    {
        $result = $this->call('sw_register', 'domain', [
            'domain' => $domain,
            'reg_type' => 'transfer',
            'auth_info' => $authCode,
            'period' => 1,
        ]);

        $code = (int) ($result['response_code'] ?? 0);
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('OpenSRS transfer failed: '.($result['response_text'] ?? 'Unknown'));
        }

        return [
            'success' => true,
            'transfer_id' => $result['attributes']['id'] ?? '',
        ];
    }

    public function getNameservers(string $domain): array
    {
        $result = $this->call('get', 'domain', [
            'domain' => $domain,
            'type' => 'nameservers',
        ]);

        $nsList = $result['attributes']['nameserver_list'] ?? [];

        return array_column($nsList, 'name');
    }

    public function setNameservers(string $domain, array $nameservers): void
    {
        $nsList = array_values(array_map(fn ($ns, $i) => ['name' => $ns, 'sortorder' => $i], $nameservers, array_keys($nameservers)));

        $this->call('advanced_update_nameservers', 'domain', [
            'domain' => $domain,
            'op' => 'add_remove',
            'assign_ns' => $nsList,
        ]);
    }

    public function getInfo(string $domain): array
    {
        $result = $this->call('get', 'domain', [
            'domain' => $domain,
            'type' => 'all_info',
        ]);

        $attrs = $result['attributes'] ?? [];
        $ns = array_column($attrs['nameserver_list'] ?? [], 'name');

        return [
            'expires_at' => $attrs['expiredate'] ?? '',
            'locked' => ($attrs['registry_registrant_id'] ?? '') !== '',
            'privacy' => strtolower($attrs['whois_privacy'] ?? 'disable') === 'enable',
            'nameservers' => $ns,
        ];
    }

    public function setLock(string $domain, bool $locked): void
    {
        $this->call('set_registrar_lock', 'domain', [
            'domain' => $domain,
            'operate' => $locked ? 'lock' : 'unlock',
        ]);
    }

    public function setPrivacy(string $domain, bool $enabled): void
    {
        $this->call('set_whois_privacy_state', 'domain', [
            'domain' => $domain,
            'state' => $enabled ? 'enable' : 'disable',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function call(string $action, string $object, array $attributes = []): array
    {
        $body = $this->buildXml($action, $object, $attributes);

        $md5Key = md5($this->apiKey);
        $sig = md5(md5($body.$md5Key).$md5Key);

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml',
            'X-Username' => $this->reseller,
            'X-Signature' => $sig,
            'Content-Length' => strlen($body),
        ])->withBody($body, 'text/xml')
            ->post($this->baseUrl);

        if (! $response->successful()) {
            throw new RuntimeException("OpenSRS HTTP error [{$action}]: {$response->status()}");
        }

        return $this->parseXml($response->body());
    }

    private function buildXml(string $action, string $object, array $attributes): string
    {
        $attrsXml = $this->arrayToXml($attributes);

        return <<<XML
<?xml version='1.0' encoding='UTF-8' standalone='no'?>
<!DOCTYPE OPS_envelope SYSTEM 'ops.dtd'>
<OPS_envelope>
  <header>
    <version>0.9</version>
  </header>
  <body>
    <data_block>
      <dt_assoc>
        <item key="protocol">XCP</item>
        <item key="action">{$action}</item>
        <item key="object">{$object}</item>
        <item key="attributes">
          <dt_assoc>
            {$attrsXml}
          </dt_assoc>
        </item>
      </dt_assoc>
    </data_block>
  </body>
</OPS_envelope>
XML;
    }

    private function arrayToXml(array $data, int $depth = 0): string
    {
        $xml = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $inner = $this->arrayToXml($value, $depth + 1);
                $xml .= "<item key=\"{$key}\"><dt_assoc>{$inner}</dt_assoc></item>";
            } else {
                $escaped = htmlspecialchars((string) $value, ENT_XML1);
                $xml .= "<item key=\"{$key}\">{$escaped}</item>";
            }
        }

        return $xml;
    }

    private function parseXml(string $xml): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            throw new RuntimeException('OpenSRS returned unparseable XML response.');
        }

        $result = json_decode(json_encode($doc), true);

        $body = $result['body']['data_block']['dt_assoc']['item'] ?? [];

        $flat = [];
        foreach ($body as $item) {
            $key = $item['@attributes']['key'] ?? null;
            $value = $item['dt_assoc']['item'] ?? $item['_'] ?? $item;
            if ($key) {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }

    private function buildContact(array $c): array
    {
        return [
            'first_name' => $c['registrant_first'],
            'last_name' => $c['registrant_last'],
            'email' => $c['registrant_email'],
            'phone' => $c['registrant_phone'],
            'address1' => $c['registrant_address'],
            'city' => $c['registrant_city'],
            'state' => $c['registrant_state'],
            'postal_code' => $c['registrant_zip'],
            'country' => $c['registrant_country'],
            'org_name' => $c['registrant_first'].' '.$c['registrant_last'],
        ];
    }

    /**
     * Fetch reseller cost pricing for all TLDs.
     * Uses the get_product_list / domain action (OpenSRS XCP API).
     *
     * @return array<string, array{register: float|null, renew: float|null, transfer: float|null, currency: string}>
     */
    public function getPricing(): array
    {
        $pricing = [];

        try {
            $result = $this->call('get_product_list', 'domain', [
                'product_type' => 'domain_registration',
            ]);

            // Response: attributes.products[] each with tld, register, renew, transfer prices
            $products = $result['attributes']['products'] ?? [];

            foreach ($products as $product) {
                $tld = strtolower(ltrim($product['tld'] ?? '', '.'));
                if ($tld === '') {
                    continue;
                }

                $pricing[$tld] = [
                    'register' => isset($product['price_register']) ? (float) $product['price_register'] : null,
                    'renew' => isset($product['price_renew']) ? (float) $product['price_renew'] : null,
                    'transfer' => isset($product['price_transfer']) ? (float) $product['price_transfer'] : null,
                    'currency' => $product['currency'] ?? 'USD',
                ];
            }
        } catch (\Throwable) {
            // API unavailable or response format changed
        }

        return $pricing;
    }

    private function splitDomain(string $domain): array
    {
        $parts = explode('.', $domain, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
