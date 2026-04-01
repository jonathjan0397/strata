<?php

namespace App\Services\Provisioners;

use App\Contracts\ProvisionerDriver;
use App\Models\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * HestiaCP REST API provisioner.
 * Docs: https://hestiacp.com/docs/api/
 * Authentication: admin + API key via POST parameters (hash).
 */
class HestiaProvisioner implements ProvisionerDriver
{
    private string $baseUrl;

    private string $adminUser;

    private string $apiKey;

    private bool $skipVerify;

    public function __construct(private readonly Module $module)
    {
        $host = $module->local_hostname ?: $module->hostname;
        $port = $module->local_port     ?? $module->port;
        $scheme = $module->ssl ? 'https' : 'http';
        $this->baseUrl    = "{$scheme}://{$host}:{$port}/api/v1";
        $this->skipVerify = (bool) $module->local_hostname;

        $config = $module->module_config ?? [];
        $this->adminUser = $config['admin_user'] ?? 'admin';
        $this->apiKey = decrypt($module->api_token_enc);
    }

    public function slug(): string
    {
        return 'hestia';
    }

    public static function findAvailableModule(): ?Module
    {
        return Module::where('type', 'hestia')
            ->where('active', true)
            ->get()
            ->first(fn (Module $m) => $m->hasCapacity());
    }

    public function createAccount(string $domain, ?string $plan = null): array
    {
        $username = $this->generateUsername($domain);
        $password = Str::password(16, symbols: false);

        // v-add-user USER PASSWORD EMAIL [PACKAGE] [FNAME] [LNAME]
        $result = $this->call('v-add-user', [
            $username,
            $password,
            "admin@{$domain}",
            $plan ?? 'default',
            $username,
            'User',
        ]);

        if (($result['status'] ?? '') !== 'ok') {
            throw new RuntimeException('HestiaCP account creation failed: '.($result['error'] ?? 'Unknown error'));
        }

        // v-add-web-domain USER DOMAIN
        $this->call('v-add-web-domain', [$username, $domain]);

        return [
            'username' => $username,
            'password' => $password,
            'domain' => $domain,
        ];
    }

    public function suspendAccount(string $username, string $reason = 'Billing'): void
    {
        // v-suspend-user USER
        $result = $this->call('v-suspend-user', [$username]);

        if (isset($result['status']) && $result['status'] !== 'ok') {
            throw new RuntimeException('HestiaCP suspend failed: '.($result['error'] ?? 'Unknown'));
        }
    }

    public function unsuspendAccount(string $username): void
    {
        // v-unsuspend-user USER
        $result = $this->call('v-unsuspend-user', [$username]);

        if (isset($result['status']) && $result['status'] !== 'ok') {
            throw new RuntimeException('HestiaCP unsuspend failed: '.($result['error'] ?? 'Unknown'));
        }
    }

    public function terminateAccount(string $username): void
    {
        // v-delete-user USER
        $result = $this->call('v-delete-user', [$username]);

        if (isset($result['status']) && $result['status'] !== 'ok') {
            throw new RuntimeException('HestiaCP delete failed: '.($result['error'] ?? 'Unknown'));
        }
    }

    public function listAccounts(): array
    {
        $response = Http::asForm()
            ->withOptions(['verify' => $this->module->ssl && ! $this->skipVerify])
            ->timeout(30)
            ->post("{$this->baseUrl}/", [
                'user'       => $this->adminUser,
                'password'   => $this->apiKey,
                'returncode' => 'no',
                'cmd'        => 'v-list-users',
                'arg1'       => 'json',  // positional arg: format
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("HestiaCP v-list-users failed: {$response->status()}");
        }

        $users = $response->json() ?? [];
        $accounts = [];

        foreach ($users as $username => $info) {
            if (! is_array($info)) {
                continue;
            }
            $accounts[] = [
                'username'  => $username,
                'domain'    => $info['DOMAIN'] ?? '',
                'email'     => $info['EMAIL'] ?? '',
                'plan'      => $info['PACKAGE'] ?? '',
                'suspended' => strtoupper($info['SUSPENDED'] ?? 'NO') === 'YES',
            ];
        }

        return $accounts;
    }

    public function listPackages(): array
    {
        $response = Http::asForm()
            ->withOptions(['verify' => $this->module->ssl && ! $this->skipVerify])
            ->timeout(30)
            ->post("{$this->baseUrl}/", [
                'user'       => $this->adminUser,
                'password'   => $this->apiKey,
                'returncode' => 'no',
                'cmd'        => 'v-list-packages',
                'arg1'       => 'json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("HestiaCP v-list-packages failed: {$response->status()}");
        }

        $packages = $response->json() ?? [];
        $result = [];

        foreach ($packages as $name => $info) {
            if (! is_array($info)) {
                continue;
            }
            $result[] = [
                'name'         => $name,
                'disk_mb'      => (int) ($info['DISK'] ?? 0),
                'bandwidth_mb' => (int) ($info['BANDWIDTH'] ?? 0),
            ];
        }

        return $result;
    }

    public function packageExists(string $name): bool
    {
        $packages = $this->listPackages();

        return collect($packages)->contains(fn ($p) => $p['name'] === $name);
    }

    public function createPackage(string $name, array $config = []): void
    {
        $disk      = (int) ($config['disk_mb'] ?? 1024);
        $bandwidth = (int) ($config['bandwidth_mb'] ?? 10240);

        // v-add-package NAME [WEB_TEMPLATE] [DNS_TEMPLATE] [MAIL_TEMPLATE] [DATABASES] [CRONTABS] [BACKUPS] [BANDWIDTH] [DISK]
        $result = $this->call('v-add-package', [
            $name,
            'default',   // web template
            'default',   // dns template
            'default',   // mail template
            'unlimited', // databases
            'unlimited', // crontabs
            'unlimited', // backups
            $bandwidth,
            $disk,
        ]);

        if (($result['status'] ?? '') !== 'ok') {
            throw new RuntimeException('HestiaCP v-add-package failed: '.($result['error'] ?? 'Unknown'));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** Call a HestiaCP API command. $args are positional: arg1, arg2, ... */
    private function call(string $cmd, array $args = []): array
    {
        $payload = [
            'user'       => $this->adminUser,
            'password'   => $this->apiKey,
            'returncode' => 'yes',
            'cmd'        => $cmd,
        ];

        foreach ($args as $i => $val) {
            $payload['arg'.($i + 1)] = $val;
        }

        $response = Http::asForm()
            ->withOptions(['verify' => $this->module->ssl && ! $this->skipVerify])
            ->timeout(20)
            ->post("{$this->baseUrl}/", $payload);

        if (! $response->successful()) {
            throw new RuntimeException("HestiaCP HTTP error [{$cmd}]: {$response->status()}");
        }

        $body = trim($response->body());

        // HestiaCP returns '0' for success or a non-zero integer error code
        if (is_numeric($body)) {
            return ['status' => $body === '0' ? 'ok' : 'error', 'error' => $body];
        }

        return $response->json() ?? ['status' => 'ok'];
    }

    private function generateUsername(string $domain): string
    {
        $base = preg_replace('/\.[^.]+$/', '', $domain);
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($base));
        $base = substr($base, 0, 6);

        return $base.Str::lower(Str::random(2));
    }
}
