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

    public function __construct(private readonly Module $module)
    {
        $scheme = $module->ssl ? 'https' : 'http';
        $this->baseUrl = "{$scheme}://{$module->hostname}:{$module->port}";

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

    public function createAccount(string $domain, ?string $plan = null): array
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

    // ─────────────────────────────────────────────────────────────────────────

    private function request(string $method, string $path, array $params = []): array
    {
        $req = Http::withBasicAuth($this->user, $this->password)
            ->withOptions(['verify' => $this->module->ssl])
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
