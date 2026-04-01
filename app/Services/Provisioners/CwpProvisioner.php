<?php

namespace App\Services\Provisioners;

use App\Contracts\ProvisionerDriver;
use App\Models\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Control Web Panel (CWP) REST API provisioner.
 * Docs: https://docs.control-webpanel.com/docs/developer-tools/api-manager
 *
 * Base URL: https://{hostname}:{port}/v1/
 * Default port: 2304
 * Auth: API key sent as `key` in every request body / query string.
 */
class CwpProvisioner implements ProvisionerDriver
{
    private string $baseUrl;

    private string $apiKey;

    private bool $skipVerify;

    public function __construct(private readonly Module $module)
    {
        $host = $module->local_hostname ?: $module->hostname;
        $port = $module->local_port     ?? $module->port;
        $scheme = $module->ssl ? 'https' : 'http';
        $this->baseUrl    = "{$scheme}://{$host}:{$port}/v1";
        $this->apiKey     = decrypt($module->api_token_enc);
        $this->skipVerify = (bool) $module->local_hostname;
    }

    public function slug(): string
    {
        return 'cwp';
    }

    public static function findAvailableModule(): ?Module
    {
        return Module::where('type', 'cwp')
            ->where('active', true)
            ->get()
            ->first(fn (Module $m) => $m->hasCapacity());
    }

    public function createAccount(string $domain, ?string $plan = null): array
    {
        $username = $this->generateUsername($domain);
        $password = Str::password(16, symbols: false);

        $params = [
            'action'      => 'add',
            'domain'      => $domain,
            'username'    => $username,
            'password'    => $password,
            'email'       => "admin@{$domain}",
            'package'     => $plan ?? 'default',
            'inode'       => 0,
            'limit_nproc' => 40,
            'limit_nofile' => 150,
            'enctype'     => 'md5',
        ];

        $data = $this->request('POST', '/account', $params);

        if (($data['status'] ?? '') !== 'OK') {
            $msg = $data['msj'] ?? ($data['error'] ?? 'Unknown CWP error');
            throw new RuntimeException("CWP account creation failed: {$msg}");
        }

        return [
            'username' => $username,
            'password' => $password,
            'domain'   => $domain,
        ];
    }

    public function suspendAccount(string $username, string $reason = 'Billing'): void
    {
        $data = $this->request('POST', '/account', [
            'action' => 'susp',
            'user'   => $username,
        ]);

        if (($data['status'] ?? '') !== 'OK') {
            throw new RuntimeException('CWP suspend failed: '.($data['msj'] ?? 'Unknown error'));
        }
    }

    public function unsuspendAccount(string $username): void
    {
        $data = $this->request('POST', '/account', [
            'action' => 'unsp',
            'user'   => $username,
        ]);

        if (($data['status'] ?? '') !== 'OK') {
            throw new RuntimeException('CWP unsuspend failed: '.($data['msj'] ?? 'Unknown error'));
        }
    }

    public function terminateAccount(string $username): void
    {
        $data = $this->request('POST', '/account', [
            'action' => 'del',
            'user'   => $username,
        ]);

        if (($data['status'] ?? '') !== 'OK') {
            throw new RuntimeException('CWP delete failed: '.($data['msj'] ?? 'Unknown error'));
        }
    }

    public function listAccounts(): array
    {
        $data     = $this->request('POST', '/account', ['action' => 'list']);
        $accounts = $data['msj'] ?? [];

        if (! is_array($accounts)) {
            return [];
        }

        return array_map(fn ($a) => [
            'username'  => $a['username'] ?? '',
            'domain'    => $a['domain']   ?? '',
            'email'     => $a['email']    ?? '',
            'plan'      => $a['package']  ?? '',
            'suspended' => strtolower($a['status'] ?? 'active') === 'suspended',
        ], $accounts);
    }

    public function listPackages(): array
    {
        $data     = $this->request('POST', '/package', ['action' => 'list']);
        $packages = $data['msj'] ?? [];

        if (! is_array($packages)) {
            return [];
        }

        return array_map(fn ($p) => [
            'name'         => $p['name']      ?? '',
            'disk_mb'      => $this->parseLimit($p['diskspace'] ?? '0'),
            'bandwidth_mb' => $this->parseLimit($p['bandwidth'] ?? '0'),
        ], array_filter($packages, fn ($p) => ! empty($p['name'])));
    }

    public function packageExists(string $name): bool
    {
        $packages = $this->listPackages();

        return collect($packages)->contains(fn ($p) => $p['name'] === $name);
    }

    public function createPackage(string $name, array $config = []): void
    {
        $data = $this->request('POST', '/package', [
            'action'         => 'add',
            'name'           => $name,
            'diskspace'      => (int) ($config['disk_mb'] ?? 1024),
            'bandwidth'      => (int) ($config['bandwidth_mb'] ?? 10240),
            'ftpaccounts'    => 'unlimited',
            'emailaccounts'  => 'unlimited',
            'mysqldatabases' => 'unlimited',
            'subdomains'     => 'unlimited',
            'parkeddomains'  => 'unlimited',
            'addondomains'   => 'unlimited',
            'inodes'         => 0,
            'dailybackup'    => 'off',
        ]);

        if (($data['status'] ?? '') !== 'OK') {
            $msg = $data['msj'] ?? ($data['error'] ?? 'Unknown CWP error');
            throw new RuntimeException("CWP package creation failed: {$msg}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function request(string $method, string $path, array $params = []): array
    {
        $params['key'] = $this->apiKey;

        $req = Http::withOptions(['verify' => $this->module->ssl && ! $this->skipVerify])->timeout(20);

        $response = strtoupper($method) === 'POST'
            ? $req->asForm()->post($this->baseUrl.$path, $params)
            : $req->get($this->baseUrl.$path, $params);

        if (! $response->successful()) {
            throw new RuntimeException("CWP API HTTP error [{$path}]: {$response->status()}");
        }

        return $response->json() ?? [];
    }

    /** CWP returns 'unlimited' or a numeric string for limits. */
    private function parseLimit(string $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function generateUsername(string $domain): string
    {
        $base = preg_replace('/\.[^.]+$/', '', $domain);
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($base));
        $base = substr($base, 0, 6);

        return $base.Str::lower(Str::random(2));
    }
}
