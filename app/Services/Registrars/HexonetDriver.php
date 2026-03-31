<?php

namespace App\Services\Registrars;

use App\Contracts\RegistrarDriver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HEXONET / CentralNic Registrar Driver
 *
 * Uses the HEXONET ISPAPI HTTP gateway.
 * OTE (sandbox): https://api-ote.hexonet.net/api/call.cgi
 * Live:          https://api.hexonet.net/api/call.cgi
 *
 * Authentication: s_login + s_pw posted with every command.
 * Command format: POST body with s_command=COMMAND\nparam=value\n...
 * Response format: plain text key=value pairs under [RESPONSE] header.
 */
class HexonetDriver implements RegistrarDriver
{
    private string $login;
    private string $password;
    private string $baseUrl;

    public function __construct()
    {
        $sandbox = config('registrars.hexonet.sandbox', true);

        $this->login    = config('registrars.hexonet.login', '');
        $this->password = config('registrars.hexonet.password', '');
        $this->baseUrl  = $sandbox
            ? 'https://api-ote.hexonet.net/api/call.cgi'
            : 'https://api.hexonet.net/api/call.cgi';
    }

    public function slug(): string
    {
        return 'hexonet';
    }

    public function checkAvailability(string $domain): array
    {
        $response = $this->call("CheckDomains\ndomain0={$domain}");
        $check    = $response['property']['DOMAINCHECK'][0] ?? '';

        // HEXONET returns "210 domain available" or "211 domain not available"
        $available = str_starts_with(trim($check), '210');

        return ['available' => $available];
    }

    public function registerDomain(string $domain, int $years, array $contact): array
    {
        $nameservers = $contact['nameservers'] ?? ['ns1.hexonet.net', 'ns2.hexonet.net'];

        $cmd  = "AddDomain\n";
        $cmd .= "domain={$domain}\n";
        $cmd .= "period={$years}\n";

        foreach (array_values($nameservers) as $i => $ns) {
            $cmd .= "nameserver{$i}={$ns}\n";
        }

        // Inline contact fields — HEXONET accepts them directly on AddDomain
        $contactLines = $this->buildContactLines($contact);
        foreach (['ownercontact0', 'admincontact0', 'techcontact0', 'billingcontact0'] as $type) {
            foreach ($contactLines as $key => $val) {
                $cmd .= "{$type}-{$key}={$val}\n";
            }
        }

        $response = $this->call(rtrim($cmd));

        if ((int) $response['code'] !== 200) {
            throw new RuntimeException('HEXONET domain registration failed: '.($response['description'] ?? 'unknown error'));
        }

        return [
            'success'        => true,
            'registrar_data' => [
                'domain'     => $domain,
                'object_id'  => $response['property']['OBJECTID'][0] ?? null,
                'created_at' => $response['property']['CREATEDDATE'][0] ?? null,
            ],
        ];
    }

    public function renewDomain(string $domain, int $years): array
    {
        $response = $this->call("RenewDomain\ndomain={$domain}\nperiod={$years}");

        if ((int) $response['code'] !== 200) {
            throw new RuntimeException('HEXONET domain renewal failed: '.($response['description'] ?? 'unknown error'));
        }

        return [
            'success'    => true,
            'expires_at' => $response['property']['EXPIRATIONDATE'][0] ?? '',
        ];
    }

    public function transferDomain(string $domain, string $authCode): array
    {
        $response = $this->call("TransferDomain\ndomain={$domain}\nauth={$authCode}\naction=REQUEST");

        if ((int) $response['code'] !== 200 && (int) $response['code'] !== 202) {
            throw new RuntimeException('HEXONET domain transfer failed: '.($response['description'] ?? 'unknown error'));
        }

        return [
            'success'     => true,
            'transfer_id' => $response['property']['TRANSFERID'][0] ?? $domain,
        ];
    }

    public function getNameservers(string $domain): array
    {
        $response = $this->call("StatusDomain\ndomain={$domain}");

        if ((int) $response['code'] !== 200) {
            throw new RuntimeException('HEXONET StatusDomain failed: '.($response['description'] ?? 'unknown error'));
        }

        return $response['property']['NAMESERVER'] ?? [];
    }

    public function setNameservers(string $domain, array $nameservers): void
    {
        $cmd = "ModifyDomain\ndomain={$domain}\ndelallnameservers=1\n";

        foreach (array_values($nameservers) as $i => $ns) {
            $cmd .= "nameserver{$i}={$ns}\n";
        }

        $response = $this->call(rtrim($cmd));

        if ((int) $response['code'] !== 200) {
            throw new RuntimeException('HEXONET setNameservers failed: '.($response['description'] ?? 'unknown error'));
        }
    }

    public function getInfo(string $domain): array
    {
        $response = $this->call("StatusDomain\ndomain={$domain}");

        if ((int) $response['code'] !== 200) {
            throw new RuntimeException('HEXONET StatusDomain failed: '.($response['description'] ?? 'unknown error'));
        }

        $p = $response['property'];

        return [
            'expires_at'  => $p['EXPIRATIONDATE'][0] ?? '',
            'locked'      => (($p['TRANSFERLOCK'][0] ?? '0') === '1'),
            'privacy'     => strtolower($p['X-WHOISGUARD'][0] ?? '0') !== '0',
            'nameservers' => $p['NAMESERVER'] ?? [],
        ];
    }

    public function setLock(string $domain, bool $locked): void
    {
        $cmd = "ModifyDomain\ndomain={$domain}\ntransferlock=".($locked ? '1' : '0');
        $response = $this->call($cmd);

        if ((int) $response['code'] !== 200) {
            throw new RuntimeException('HEXONET setLock failed: '.($response['description'] ?? 'unknown error'));
        }
    }

    public function setPrivacy(string $domain, bool $enabled): void
    {
        $cmd = "ModifyDomain\ndomain={$domain}\nx-whoisguard=".($enabled ? '1' : '0');
        $response = $this->call($cmd);

        if ((int) $response['code'] !== 200) {
            throw new RuntimeException('HEXONET setPrivacy failed: '.($response['description'] ?? 'unknown error'));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Execute a HEXONET API command and parse the response.
     *
     * @return array{code: string, description: string, property: array<string, string[]>}
     */
    private function call(string $command): array
    {
        $body = http_build_query([
            's_login'   => $this->login,
            's_pw'      => $this->password,
            's_command' => $command,
        ]);

        $raw = Http::withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
            ->withBody($body, 'application/x-www-form-urlencoded')
            ->post($this->baseUrl)
            ->body();

        return $this->parseResponse($raw);
    }

    /**
     * Parse the HEXONET plain-text response into a structured array.
     *
     * Response format:
     *   [RESPONSE]
     *   CODE = 200
     *   DESCRIPTION = Command completed successfully
     *   PROPERTY[NAMESERVER][0] = ns1.example.com
     *   EOF
     */
    private function parseResponse(string $raw): array
    {
        $result = [
            'code'        => '500',
            'description' => 'No response from HEXONET API',
            'property'    => [],
        ];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '' || $line === '[RESPONSE]' || $line === 'EOF') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key   = trim($key);
            $value = trim($value);

            if (strtoupper($key) === 'CODE') {
                $result['code'] = $value;
            } elseif (strtoupper($key) === 'DESCRIPTION') {
                $result['description'] = $value;
            } elseif (preg_match('/^PROPERTY\[([A-Z0-9_-]+)\]\[(\d+)\]$/i', $key, $m)) {
                $result['property'][strtoupper($m[1])][(int) $m[2]] = $value;
            }
        }

        return $result;
    }

    /**
     * Build inline contact fields from the standard contact array.
     *
     * @param  array{registrant_first: string, registrant_last: string, ...}  $contact
     * @return array<string, string>
     */
    private function buildContactLines(array $c): array
    {
        return [
            'firstname'   => $c['registrant_first']   ?? '',
            'lastname'    => $c['registrant_last']     ?? '',
            'email'       => $c['registrant_email']    ?? '',
            'phone'       => $c['registrant_phone']    ?? '',
            'street'      => $c['registrant_address']  ?? '',
            'city'        => $c['registrant_city']     ?? '',
            'state'       => $c['registrant_state']    ?? '',
            'zip'         => $c['registrant_zip']      ?? '',
            'country'     => $c['registrant_country']  ?? '',
        ];
    }

    /**
     * Fetch reseller cost pricing for all TLDs.
     * Uses QueryDomainPricelist (HEXONET ISPAPI API).
     *
     * Response properties:
     *   PROPERTY[CLASS][n]         — TLD identifier (e.g. "DOMAIN_COM")
     *   PROPERTY[PRICEREGISTER][n] — register cost
     *   PROPERTY[PRICERENEW][n]    — renew cost
     *   PROPERTY[PRICETRANSFER][n] — transfer cost
     *   PROPERTY[CURRENCY][n]      — currency code
     *
     * @return array<string, array{register: float|null, renew: float|null, transfer: float|null, currency: string}>
     */
    public function getPricing(): array
    {
        $pricing = [];

        try {
            $response = $this->call("QueryDomainPricelist\ncurrency=USD");

            $prop  = $response['property'];
            $count = count($prop['CLASS'] ?? []);

            for ($i = 0; $i < $count; $i++) {
                // CLASS is like "DOMAIN_COM" — extract TLD after first underscore
                $class = strtolower($prop['CLASS'][$i] ?? '');
                $tld   = ltrim(substr($class, (int) strpos($class, '_')), '_');

                if ($tld === '') {
                    continue;
                }

                $pricing[$tld] = [
                    'register' => isset($prop['PRICEREGISTER'][$i]) ? (float) $prop['PRICEREGISTER'][$i] : null,
                    'renew'    => isset($prop['PRICERENEW'][$i])    ? (float) $prop['PRICERENEW'][$i]    : null,
                    'transfer' => isset($prop['PRICETRANSFER'][$i]) ? (float) $prop['PRICETRANSFER'][$i] : null,
                    'currency' => $prop['CURRENCY'][$i] ?? 'USD',
                ];
            }
        } catch (\Throwable) {
            // API unavailable or response format changed
        }

        return $pricing;
    }
}
