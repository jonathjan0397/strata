<?php

namespace App\Services\Provisioners;

use App\Contracts\ProvisionerDriver;
use App\Models\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CpanelProvisioner implements ProvisionerDriver
{
    private string $baseUrl;

    private string $token;

    private bool $skipVerify;

    public function __construct(private readonly Module $module)
    {
        $host = $module->local_hostname ?: $module->hostname;
        $port = $module->local_port     ?? $module->port;
        $scheme = $module->ssl ? 'https' : 'http';
        $this->baseUrl    = "{$scheme}://{$host}:{$port}/json-api";
        $this->token      = decrypt($module->api_token_enc);
        $this->skipVerify = (bool) $module->local_hostname;
    }

    private function req(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['Authorization' => "whm {$this->module->username}:{$this->token}"])
            ->withOptions(['verify' => $this->module->ssl && ! $this->skipVerify])
            ->timeout(30);
    }

    public function slug(): string
    {
        return 'cpanel';
    }

    public static function findAvailableModule(): ?Module
    {
        return Module::where('type', 'cpanel')
            ->where('active', true)
            ->get()
            ->first(fn (Module $m) => $m->hasCapacity());
    }

    public function createAccount(string $domain, ?string $plan = null): array
    {
        $username = $this->generateUsername($domain);
        $password = Str::password(16, symbols: false);

        $params = [
            'username' => $username,
            'domain' => $domain,
            'password' => $password,
            'contactemail' => '',
            'savepwd' => 0,
        ];

        if ($plan) {
            $params['plan'] = $plan;
        }

        $response = $this->req()->get("{$this->baseUrl}/createacct", $params);

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
            'domain' => $domain,
        ];
    }

    public function suspendAccount(string $username, string $reason = 'Billing'): void
    {
        $response = $this->req()->get("{$this->baseUrl}/suspendacct", [
            'user' => $username,
            'reason' => $reason,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("WHM suspend failed: {$response->status()}");
        }
    }

    public function unsuspendAccount(string $username): void
    {
        $response = $this->req()->get("{$this->baseUrl}/unsuspendacct", ['user' => $username]);

        if (! $response->successful()) {
            throw new RuntimeException("WHM unsuspend failed: {$response->status()}");
        }
    }

    public function terminateAccount(string $username): void
    {
        $response = $this->req()->get("{$this->baseUrl}/removeacct", ['user' => $username]);

        if (! $response->successful()) {
            throw new RuntimeException("WHM terminate failed: {$response->status()}");
        }
    }

    public function listAccounts(): array
    {
        $response = $this->req()->get("{$this->baseUrl}/listaccts", ['want' => 'user,domain,email,plan,suspended,diskused']);

        if (! $response->successful()) {
            throw new RuntimeException("WHM listaccts failed: {$response->status()}");
        }

        $accounts = $response->json('data.acct') ?? [];

        return array_map(fn ($a) => [
            'username'  => $a['user'] ?? '',
            'domain'    => $a['domain'] ?? '',
            'email'     => $a['email'] ?? '',
            'plan'      => $a['plan'] ?? '',
            'suspended' => (bool) ($a['suspended'] ?? false),
        ], $accounts);
    }

    public function listPackages(): array
    {
        $response = $this->req()->get("{$this->baseUrl}/listpkgs");

        if (! $response->successful()) {
            throw new RuntimeException("WHM listpkgs failed: {$response->status()}");
        }

        $packages = $response->json('data.pkg') ?? [];

        return array_map(fn ($p) => [
            'name'         => $p['name'] ?? '',
            'disk_mb'      => (int) ($p['QUOTA'] ?? 0),
            'bandwidth_mb' => (int) ($p['BWLIMIT'] ?? 0),
        ], $packages);
    }

    public function packageExists(string $name): bool
    {
        $packages = $this->listPackages();

        return collect($packages)->contains(fn ($p) => $p['name'] === $name);
    }

    public function createPackage(string $name, array $config = []): void
    {
        $params = [
            'name'     => $name,
            'QUOTA'    => (int) ($config['disk_mb'] ?? 1024),
            'BWLIMIT'  => (int) ($config['bandwidth_mb'] ?? 10240),
            'MAXPOP'   => 'unlimited',
            'MAXFTP'   => 'unlimited',
            'MAXSQL'   => 'unlimited',
            'MAXSUB'   => 'unlimited',
            'MAXPARK'  => 'unlimited',
            'MAXADDON' => 'unlimited',
        ];

        $response = $this->req()->get("{$this->baseUrl}/addpkg", $params);

        if (! $response->successful()) {
            throw new RuntimeException("WHM addpkg HTTP error: {$response->status()}");
        }

        $result = $response->json('result.0') ?? $response->json('result') ?? [];

        if (($result['status'] ?? 0) != 1) {
            throw new RuntimeException('WHM addpkg failed: '.($result['statusmsg'] ?? 'Unknown error'));
        }
    }

    private function generateUsername(string $domain): string
    {
        $base = preg_replace('/\.[^.]+$/', '', $domain);
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($base));
        $base = substr($base, 0, 6);

        return $base.Str::lower(Str::random(2));
    }
}
