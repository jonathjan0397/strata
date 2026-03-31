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

    public function __construct(private readonly Module $module)
    {
        $scheme = $module->ssl ? 'https' : 'http';
        $this->baseUrl = "{$scheme}://{$module->hostname}:{$module->port}/api/v1";

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

        $params = [
            'user' => $username,
            'password' => $password,
            'email' => "admin@{$domain}",
            'package' => $plan ?? 'default',
            'fname' => $username,
            'lname' => 'User',
        ];

        $result = $this->call('add', 'user', $params);

        if (($result['status'] ?? '') !== 'ok') {
            throw new RuntimeException('HestiaCP account creation failed: '.($result['error'] ?? 'Unknown error'));
        }

        // Add the domain to the new user
        $this->call('add', 'web', [
            'user' => $username,
            'domain' => $domain,
        ]);

        return [
            'username' => $username,
            'password' => $password,
            'domain' => $domain,
        ];
    }

    public function suspendAccount(string $username, string $reason = 'Billing'): void
    {
        $result = $this->call('suspend', 'user', ['user' => $username]);

        if (isset($result['status']) && $result['status'] !== 'ok') {
            throw new RuntimeException('HestiaCP suspend failed: '.($result['error'] ?? 'Unknown'));
        }
    }

    public function unsuspendAccount(string $username): void
    {
        $result = $this->call('unsuspend', 'user', ['user' => $username]);

        if (isset($result['status']) && $result['status'] !== 'ok') {
            throw new RuntimeException('HestiaCP unsuspend failed: '.($result['error'] ?? 'Unknown'));
        }
    }

    public function terminateAccount(string $username): void
    {
        $result = $this->call('delete', 'user', [
            'user' => $username,
            'purge' => 'yes',
        ]);

        if (isset($result['status']) && $result['status'] !== 'ok') {
            throw new RuntimeException('HestiaCP delete failed: '.($result['error'] ?? 'Unknown'));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function call(string $action, string $object, array $params = []): array
    {
        $response = Http::asForm()
            ->withOptions(['verify' => $this->module->ssl])
            ->timeout(20)
            ->post("{$this->baseUrl}/", array_merge([
                'user' => $this->adminUser,
                'password' => $this->apiKey,
                'returncode' => 'yes',
                'cmd' => "v-{$action}-{$object}",
            ], $params));

        if (! $response->successful()) {
            throw new RuntimeException("HestiaCP HTTP error [{$action}-{$object}]: {$response->status()}");
        }

        $body = trim($response->body());

        // HestiaCP returns '0' for success or an error code integer
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
