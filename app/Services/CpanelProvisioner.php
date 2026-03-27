<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CpanelProvisioner
{
    private string $baseUrl;
    private string $token;

    public function __construct(private readonly Module $module)
    {
        $scheme        = $module->ssl ? 'https' : 'http';
        $this->baseUrl = "{$scheme}://{$module->hostname}:{$module->port}/json-api";
        $this->token   = decrypt($module->api_token_enc);
    }

    /**
     * Find an active cPanel module with available capacity.
     */
    public static function findAvailableModule(): ?Module
    {
        return Module::where('type', 'cpanel')
            ->where('active', true)
            ->get()
            ->first(fn (Module $m) => $m->hasCapacity());
    }

    /**
     * Create a cPanel account via WHM API.
     *
     * @return array{username: string, password: string, domain: string}
     * @throws RuntimeException
     */
    public function createAccount(string $domain, ?string $plan = null): array
    {
        $username = $this->generateUsername($domain);
        $password = Str::password(16, symbols: false);

        $params = [
            'username'      => $username,
            'domain'        => $domain,
            'password'      => $password,
            'contactemail'  => '',
            'savepwd'       => 0,
        ];

        if ($plan) {
            $params['plan'] = $plan;
        }

        $response = Http::withToken($this->token)
            ->withOptions(['verify' => $this->module->ssl])
            ->timeout(30)
            ->get("{$this->baseUrl}/createacct", $params);

        if (! $response->successful()) {
            throw new RuntimeException("WHM API HTTP error: {$response->status()}");
        }

        $data = $response->json();

        $result = $data['result'][0] ?? $data['result'] ?? null;

        if (! $result || ($result['status'] ?? 0) != 1) {
            $reason = $result['statusmsg'] ?? ($data['cpanelresult']['error'] ?? 'Unknown WHM error');
            throw new RuntimeException("WHM account creation failed: {$reason}");
        }

        return [
            'username' => $username,
            'password' => $password,
            'domain'   => $domain,
        ];
    }

    /**
     * Suspend a cPanel account.
     */
    public function suspendAccount(string $username, string $reason = 'Billing'): void
    {
        $response = Http::withToken($this->token)
            ->withOptions(['verify' => $this->module->ssl])
            ->timeout(15)
            ->get("{$this->baseUrl}/suspendacct", [
                'user'   => $username,
                'reason' => $reason,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("WHM suspend failed: {$response->status()}");
        }
    }

    /**
     * Unsuspend a cPanel account.
     */
    public function unsuspendAccount(string $username): void
    {
        $response = Http::withToken($this->token)
            ->withOptions(['verify' => $this->module->ssl])
            ->timeout(15)
            ->get("{$this->baseUrl}/unsuspendacct", [
                'user' => $username,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("WHM unsuspend failed: {$response->status()}");
        }
    }

    /**
     * Terminate a cPanel account.
     */
    public function terminateAccount(string $username): void
    {
        $response = Http::withToken($this->token)
            ->withOptions(['verify' => $this->module->ssl])
            ->timeout(15)
            ->get("{$this->baseUrl}/removeacct", [
                'user' => $username,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("WHM terminate failed: {$response->status()}");
        }
    }

    private function generateUsername(string $domain): string
    {
        // Strip TLD, truncate to 8 chars, append random suffix to ensure uniqueness
        $base = preg_replace('/\.[^.]+$/', '', $domain);
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($base));
        $base = substr($base, 0, 6);

        return $base.Str::lower(Str::random(2));
    }
}
