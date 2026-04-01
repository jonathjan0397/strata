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

        // OpenSRS signature: MD5( MD5(body + apiKey) + apiKey )
        $sig = md5(md5($body.$this->apiKey).$this->apiKey);

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
            $errors = array_map(fn ($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            throw new RuntimeException('OpenSRS returned unparseable XML: '.implode('; ', $errors));
        }

        // body > data_block > dt_assoc holds the top-level response items
        $dtAssoc = $doc->body->data_block->dt_assoc ?? null;
        if ($dtAssoc === null) {
            throw new RuntimeException('OpenSRS response missing expected body/data_block/dt_assoc structure.');
        }

        return $this->parseDtAssoc($dtAssoc);
    }

    /**
     * Recursively parse an OpenSRS dt_assoc node into a PHP array.
     * dt_assoc  => associative array keyed by item/@key
     * dt_array  => numerically-indexed array keyed by item/@key (0, 1, 2 …)
     */
    private function parseDtAssoc(\SimpleXMLElement $node): array
    {
        $result = [];
        foreach ($node->item as $item) {
            $key = (string) ($item['key'] ?? '');
            $result[$key] = $this->parseItem($item);
        }

        return $result;
    }

    private function parseItem(\SimpleXMLElement $item): mixed
    {
        if (isset($item->dt_assoc)) {
            return $this->parseDtAssoc($item->dt_assoc);
        }

        if (isset($item->dt_array)) {
            $arr = [];
            foreach ($item->dt_array->item as $child) {
                $arr[] = $this->parseItem($child);
            }

            return $arr;
        }

        return (string) $item;
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
     * Fetch reseller cost pricing via per-TLD get_price calls.
     *
     * OpenSRS XCP has no bulk pricing endpoint. We query get_price for each
     * reg_type (new / renewal / transfer) across a set of common TLDs.
     *
     * @return array<string, array{register: float|null, renew: float|null, transfer: float|null, currency: string}>
     */
    public function getPricing(): array
    {
        $tlds = [
            'com', 'net', 'org', 'info', 'biz', 'us', 'ca', 'co', 'io',
            'me', 'tv', 'cc', 'mobi', 'name', 'pro',
            'app', 'dev', 'online', 'store', 'site', 'tech',
            'cloud', 'digital', 'blog',
            'uk', 'co.uk', 'org.uk', 'me.uk',
            'de', 'eu', 'fr', 'it', 'es', 'nl', 'be', 'ch', 'at',
            'au', 'com.au', 'net.au',
            'nz', 'co.nz',
            'in', 'mx', 'com.mx',
        ];

        $pricing = [];

        foreach ($tlds as $tld) {
            $domain = "zzz9test.{$tld}";

            $register = $this->fetchPrice($domain, 'new');
            $renew    = $this->fetchPrice($domain, 'renewal');
            $transfer = $this->fetchPrice($domain, 'transfer');

            if ($register !== null || $renew !== null || $transfer !== null) {
                $pricing[$tld] = [
                    'register' => $register,
                    'renew'    => $renew,
                    'transfer' => $transfer,
                    'currency' => 'USD',
                ];
            }
        }

        if (empty($pricing)) {
            throw new RuntimeException('OpenSRS returned no pricing data for any queried TLDs.');
        }

        return $pricing;
    }

    /**
     * Fetch the reseller price for one domain + reg_type. Returns null if not supported.
     */
    private function fetchPrice(string $domain, string $regType): ?float
    {
        try {
            $result = $this->call('get_price', 'domain', [
                'domain'   => $domain,
                'reg_type' => $regType,
                'period'   => 1,
            ]);

            $code = (int) ($result['response_code'] ?? 0);
            if ($code < 200 || $code >= 300) {
                return null;
            }

            $price = $result['attributes']['price'] ?? null;

            return $price !== null ? (float) $price : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function splitDomain(string $domain): array
    {
        $parts = explode('.', $domain, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
