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

    public function __construct(private readonly Module $module)
    {
        $scheme        = $module->ssl ? 'https' : 'http';
        $this->baseUrl = "{$scheme}://{$module->hostname}:{$module->port}/api/v2";
        $this->auth    = decrypt($module->api_token_enc);
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

        // Create hosting subscription
        $payload = [
            'name'                => $domain,
            'ownerLogin'          => 'admin',
            'hostingType'         => 'virtual',
            'subscriptionId'      => null,
            'ipAddresses'         => [],
        ];

        if ($plan) {
            $payload['planName'] = $plan;
        }

        // Step 1: Create the customer / webspace
        $webspaceResp = $this->request('POST', '/webspaces', [
            'name'        => $domain,
            'ownerLogin'  => 'admin',
            'hostingType' => 'virtual',
            'ip_addresses'=> [],
        ]);

        $subscriptionId = $webspaceResp['id'] ?? null;
        if (! $subscriptionId) {
            throw new RuntimeException('Plesk webspace creation did not return an ID.');
        }

        // Step 2: Create the FTP / shell user for the subscription
        $this->request('POST', '/clients', [
            'login'       => $username,
            'password'    => $password,
            'name'        => $domain,
            'email'       => "admin@{$domain}",
            'type'        => 'customer',
        ]);

        return [
            'username' => $username,
            'password' => $password,
            'domain'   => $domain,
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

    // ─────────────────────────────────────────────────────────────────────────

    private function request(string $method, string $path, array $body = []): mixed
    {
        $req = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode('admin:' . $this->auth),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->withOptions(['verify' => $this->module->ssl]);

        $response = match (strtoupper($method)) {
            'GET'    => $req->get($this->baseUrl . $path),
            'POST'   => $req->post($this->baseUrl . $path, $body),
            'PUT'    => $req->put($this->baseUrl . $path, $body),
            'DELETE' => $req->delete($this->baseUrl . $path),
            default  => throw new RuntimeException("Unknown HTTP method: {$method}"),
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
            if (($ws['name'] ?? '') === $username || ($ws['ownerLogin'] ?? '') === $username) {
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
        return $base . Str::lower(Str::random(2));
    }
}
