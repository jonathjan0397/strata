<?php

namespace App\Services\Provisioners;

use App\Contracts\ProvisionerDriver;
use App\Models\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DirectAdmin HTTP API provisioner.
 * Docs: https://www.directadmin.com/api.php
 */
class DirectAdminProvisioner implements ProvisionerDriver
{
    private string $baseUrl;

    private string $user;

    private string $password;

    private bool $skipVerify;

    public function __construct(private readonly Module $module)
    {
        $host = $module->local_hostname ?: $module->hostname;
        $port = $module->local_port     ?? $module->port;
        $scheme = $module->ssl ? 'https' : 'http';
        $this->baseUrl    = "{$scheme}://{$host}:{$port}";
        $this->skipVerify = (bool) $module->local_hostname;

        // module_config stores {"admin_user": "admin"} — password is in api_token_enc
        $config = $module->module_config ?? [];
        $this->user = $config['admin_user'] ?? 'admin';
        $this->password = decrypt($module->api_token_enc);
    }

    public function slug(): string
    {
        return 'directadmin';
    }

    public static function findAvailableModule(): ?Module
    {
        return Module::where('type', 'directadmin')
            ->where('active', true)
            ->get()
            ->first(fn (Module $m) => $m->hasCapacity());
    }

    public function createAccount(string $domain, ?string $plan = null, array $options = []): array
    {
        $username = $this->generateUsername($domain);
        $password = Str::password(16, symbols: false);

        $params = [
            'action' => 'create',
            'add' => 'Submit',
            'username' => $username,
            'email' => "admin@{$domain}",
            'passwd' => $password,
            'passwd2' => $password,
            'domain' => $domain,
            'package' => $plan ?? 'Default',
            'ip' => 'shared',
            'notify' => 'no',
        ];

        $response = $this->request('POST', '/CMD_API_ACCOUNT_USER', $params);

        if (! isset($response['error']) || $response['error'] !== '0') {
            $msg = $response['details'] ?? $response['text'] ?? 'Unknown DirectAdmin error';
            throw new RuntimeException("DirectAdmin account creation failed: {$msg}");
        }

        return [
            'username' => $username,
            'password' => $password,
            'domain' => $domain,
        ];
    }

    public function suspendAccount(string $username, string $reason = 'Billing'): void
    {
        $response = $this->request('POST', '/CMD_API_SELECT_USERS', [
            'location' => 'CMD_API_SELECT_USERS',
            'suspend' => 'Suspend',
            'select0' => $username,
        ]);

        if (isset($response['error']) && $response['error'] !== '0') {
            throw new RuntimeException('DirectAdmin suspend failed: '.($response['details'] ?? 'Unknown'));
        }
    }

    public function unsuspendAccount(string $username): void
    {
        $response = $this->request('POST', '/CMD_API_SELECT_USERS', [
            'location' => 'CMD_API_SELECT_USERS',
            'unsuspend' => 'Unsuspend',
            'select0' => $username,
        ]);

        if (isset($response['error']) && $response['error'] !== '0') {
            throw new RuntimeException('DirectAdmin unsuspend failed: '.($response['details'] ?? 'Unknown'));
        }
    }

    public function terminateAccount(string $username): void
    {
        $response = $this->request('POST', '/CMD_API_SELECT_USERS', [
            'location' => 'CMD_API_SELECT_USERS',
            'delete' => 'Delete',
            'confirmed' => 'Confirm',
            'select0' => $username,
        ]);

        if (isset($response['error']) && $response['error'] !== '0') {
            throw new RuntimeException('DirectAdmin delete failed: '.($response['details'] ?? 'Unknown'));
        }
    }

    public function listAccounts(): array
    {
        // Returns a URL-encoded list: list[]=user1&list[]=user2
        $response = $this->request('GET', '/CMD_API_SHOW_ALL_USERS');
        $usernames = $response['list'] ?? [];

        if (is_string($usernames)) {
            $usernames = [$usernames];
        }

        $accounts = [];
        foreach ($usernames as $username) {
            try {
                $details = $this->request('GET', '/CMD_API_SHOW_USER', ['user' => $username]);
                $accounts[] = [
                    'username'  => $username,
                    'domain'    => $details['domain'] ?? '',
                    'email'     => $details['email'] ?? '',
                    'plan'      => $details['package'] ?? '',
                    'suspended' => ($details['suspended'] ?? 'no') === 'yes',
                ];
            } catch (\Throwable) {
                // Skip accounts we can't fetch details for
            }
        }

        return $accounts;
    }

    public function listPackages(): array
    {
        $response = $this->request('GET', '/CMD_API_PACKAGES_USER');
        $names = $response['list'] ?? [];

        if (is_string($names)) {
            $names = [$names];
        }

        return array_map(fn ($name) => [
            'name'         => $name,
            'disk_mb'      => 0,
            'bandwidth_mb' => 0,
        ], array_filter($names));
    }

    public function packageExists(string $name): bool
    {
        $packages = $this->listPackages();

        return collect($packages)->contains(fn ($p) => $p['name'] === $name);
    }

    public function createPackage(string $name, array $config = []): void
    {
        $response = $this->request('POST', '/CMD_API_MANAGE_USER_PACKAGES', [
            'action'      => 'add',
            'name'        => $name,
            'quota'       => (int) ($config['disk_mb'] ?? 1024),
            'bandwidth'   => (int) ($config['bandwidth_mb'] ?? 10240),
            'domainptr'   => 'unlimited',
            'mysql'       => 'unlimited',
            'nemailf'     => 'unlimited',
            'nemailml'    => 'unlimited',
            'nemailr'     => 'unlimited',
            'ftp'         => 'unlimited',
            'aftp'        => 'OFF',
        ]);

        if (isset($response['error']) && $response['error'] !== '0') {
            throw new RuntimeException('DirectAdmin addpkg failed: '.($response['details'] ?? $response['text'] ?? 'Unknown'));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function request(string $method, string $path, array $params = []): array
    {
        $req = Http::withBasicAuth($this->user, $this->password)
            ->withOptions(['verify' => $this->module->ssl && ! $this->skipVerify])
            ->timeout(20);

        $response = $method === 'POST'
            ? $req->asForm()->post($this->baseUrl.$path, $params)
            : $req->get($this->baseUrl.$path, $params);

        if (! $response->successful()) {
            throw new RuntimeException("DirectAdmin HTTP error [{$path}]: {$response->status()}");
        }

        // DirectAdmin returns URL-encoded responses: error=0&text=...
        $parsed = [];
        parse_str($response->body(), $parsed);

        return $parsed ?: ['error' => '0'];
    }

    private function generateUsername(string $domain): string
    {
        $base = preg_replace('/\.[^.]+$/', '', $domain);
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($base));
        $base = substr($base, 0, 6);

        return $base.Str::lower(Str::random(2));
    }
}

