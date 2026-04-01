<?php

namespace App\Services\Provisioners;

use App\Contracts\ProvisionerDriver;
use App\Models\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Plesk REST API v2 provisioner.
 * Docs: https://docs.plesk.com/en-US/obsidian/api-rpc/about-rest-api.78244/
 */
class PleskProvisioner implements ProvisionerDriver
{
    private string $baseUrl;

    private string $auth;

    private bool $skipVerify;

    public function __construct(private readonly Module $module)
    {
        $host = $module->local_hostname ?: $module->hostname;
        $port = $module->local_port     ?? $module->port;
        $scheme = $module->ssl ? 'https' : 'http';
        $this->baseUrl    = "{$scheme}://{$host}:{$port}/api/v2";
        $this->auth       = decrypt($module->api_token_enc);
        $this->skipVerify = (bool) $module->local_hostname;
    }

    public function slug(): string
    {
        return 'plesk';
    }

    public static function findAvailableModule(): ?Module
    {
        return Module::where('type', 'plesk')
            ->where('active', true)
            ->get()
            ->first(fn (Module $m) => $m->hasCapacity());
    }

    public function createAccount(string $domain, ?string $plan = null): array
    {
        $username = $this->generateUsername($domain);
        $password = Str::password(16, symbols: false);

        // Step 1: Create the webspace (subscription)
        $webspacePayload = [
            'name'        => $domain,
            'ownerLogin'  => 'admin',
            'hostingType' => 'virtual',
            'ipAddresses' => [],
        ];

        if ($plan) {
            $webspacePayload['planName'] = $plan;
        }

        $webspaceResp = $this->request('POST', '/webspaces', $webspacePayload);

        $subscriptionId = $webspaceResp['id'] ?? null;
        if (! $subscriptionId) {
            throw new RuntimeException('Plesk webspace creation did not return an ID.');
        }

        // Step 2: Create the FTP / shell user for the subscription
        $this->request('POST', '/clients', [
            'login' => $username,
            'password' => $password,
            'name' => $domain,
            'email' => "admin@{$domain}",
            'type' => 'customer',
        ]);

        return [
            'username' => $username,
            'password' => $password,
            'domain' => $domain,
        ];
    }

    public function suspendAccount(string $username, string $reason = 'Billing'): void
    {
        // Plesk uses subscription status update
        $sub = $this->findSubscriptionByUsername($username);
        if ($sub) {
            $this->request('PUT', "/webspaces/{$sub['id']}/hosting-settings", [
                'is_disabled' => true,
            ]);
        }
    }

    public function unsuspendAccount(string $username): void
    {
        $sub = $this->findSubscriptionByUsername($username);
        if ($sub) {
            $this->request('PUT', "/webspaces/{$sub['id']}/hosting-settings", [
                'is_disabled' => false,
            ]);
        }
    }

    public function terminateAccount(string $username): void
    {
        $sub = $this->findSubscriptionByUsername($username);
        if ($sub) {
            $this->request('DELETE', "/webspaces/{$sub['id']}");
        }

        // Remove the client account
        $clients = $this->request('GET', '/clients') ?? [];
        foreach ($clients as $client) {
            if (($client['login'] ?? '') === $username) {
                $this->request('DELETE', "/clients/{$client['id']}");
                break;
            }
        }
    }

    public function listAccounts(): array
    {
        $webspaces = $this->request('GET', '/webspaces') ?? [];
        $clients   = $this->request('GET', '/clients') ?? [];

        // Build a quick lookup: login → email
        $emailByLogin = [];
        foreach ($clients as $client) {
            $emailByLogin[$client['login'] ?? ''] = $client['email'] ?? '';
        }

        return array_map(fn ($ws) => [
            'username'  => $ws['ownerClient']['login'] ?? $ws['name'] ?? '',
            'domain'    => $ws['name'] ?? '',
            'email'     => $emailByLogin[$ws['ownerClient']['login'] ?? ''] ?? '',
            'plan'      => $ws['planName'] ?? '',
            'suspended' => (bool) ($ws['is_disabled'] ?? false),
        ], $webspaces);
    }

    public function listPackages(): array
    {
        $plans = $this->request('GET', '/service-plans') ?? [];

        return array_map(fn ($p) => [
            'name'         => $p['name'] ?? '',
            'disk_mb'      => 0,
            'bandwidth_mb' => 0,
        ], $plans);
    }

    public function packageExists(string $name): bool
    {
        $packages = $this->listPackages();

        return collect($packages)->contains(fn ($p) => $p['name'] === $name);
    }

    public function createPackage(string $name, array $config = []): void
    {
        $diskBytes      = (int) ($config['disk_mb'] ?? 1024) * 1024 * 1024;
        $bandwidthBytes = (int) ($config['bandwidth_mb'] ?? 10240) * 1024 * 1024;

        $this->request('POST', '/service-plans', [
            'name'    => $name,
            'limits'  => [
                'disk_space'      => ['value' => $diskBytes,      'unit' => 'bytes'],
                'traffic'         => ['value' => $bandwidthBytes, 'unit' => 'bytes'],
                'max_subdomains'  => ['value' => -1],
                'max_dom_aliases' => ['value' => -1],
                'max_databases'   => ['value' => -1],
                'max_mailboxes'   => ['value' => -1],
                'max_ftp_users'   => ['value' => -1],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function request(string $method, string $path, array $body = []): mixed
    {
        $req = Http::withHeaders([
            'X-API-Key'    => $this->auth,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ])->withOptions(['verify' => $this->module->ssl && ! $this->skipVerify]);

        $response = match (strtoupper($method)) {
            'GET' => $req->get($this->baseUrl.$path),
            'POST' => $req->post($this->baseUrl.$path, $body),
            'PUT' => $req->put($this->baseUrl.$path, $body),
            'DELETE' => $req->delete($this->baseUrl.$path),
            default => throw new RuntimeException("Unknown HTTP method: {$method}"),
        };

        if (! $response->successful() && $method !== 'DELETE') {
            $msg = $response->json('error') ?? $response->json('message') ?? $response->status();
            throw new RuntimeException("Plesk API error [{$method} {$path}]: {$msg}");
        }

        return $response->json();
    }

    private function findSubscriptionByUsername(string $username): ?array
    {
        $webspaces = $this->request('GET', '/webspaces') ?? [];
        foreach ($webspaces as $ws) {
            if (($ws['name'] ?? '') === $username || ($ws['ownerClient']['login'] ?? '') === $username) {
                return $ws;
            }
        }

        return null;
    }

    private function generateUsername(string $domain): string
    {
        $base = preg_replace('/\.[^.]+$/', '', $domain);
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($base));
        $base = substr($base, 0, 6);

        return $base.Str::lower(Str::random(2));
    }
}
