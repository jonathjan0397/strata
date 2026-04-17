<?php

namespace App\Services\Provisioners;

use App\Contracts\ProvisionerDriver;
use App\Models\Module;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StrataPanelProvisioner implements ProvisionerDriver
{
    private string $baseUrl;

    private string $token;

    private bool $verifyTls;

    public function __construct(private readonly Module $module)
    {
        $host = $module->local_hostname ?: $module->hostname;
        $port = $module->local_port ?? $module->port;
        $scheme = $module->ssl ? 'https' : 'http';
        $base = "{$scheme}://{$host}:{$port}";

        $this->baseUrl = rtrim($base, '/').'/api/v1';
        $this->token = decrypt($module->api_token_enc);
        $verifyTls = config('strata.panel_api_verify_tls', true);
        $this->verifyTls = (bool) ($module->ssl ? $verifyTls : false);
    }

    public function slug(): string
    {
        return 'strata_panel';
    }

    public function createAccount(string $domain, ?string $plan = null, array $options = []): array
    {
        $username = $this->generateUsername($domain);
        $password = $options['password'] ?? Str::password(16, symbols: false);

        $payload = array_filter([
            'name' => $options['name'] ?? $username,
            'email' => $options['email'] ?? "hosting+{$username}@example.invalid",
            'username' => $username,
            'password' => $password,
            'hosting_package_id' => $this->resolvePackageId($plan),
            'php_version' => $options['php_version'] ?? null,
            'disk_limit_mb' => $options['disk_mb'] ?? null,
            'bandwidth_limit_mb' => $options['bandwidth_mb'] ?? null,
            'max_domains' => $options['max_domains'] ?? null,
            'max_email_accounts' => $options['max_email_accounts'] ?? null,
            'max_databases' => $options['max_databases'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->request()->post($this->baseUrl.'/accounts', $payload);

        if (! in_array($response->status(), [200, 201, 202], true)) {
            throw new RuntimeException('Strata Hosting Panel account creation failed: '.$this->responseMessage($response));
        }

        $data = $response->json();
        $createdUsername = (string) ($data['username'] ?? $username);

        $this->waitForAccountReadiness($createdUsername);

        return [
            'username' => $createdUsername,
            'password' => $password,
            'domain' => $domain,
            'remote_id' => $data['id'] ?? null,
        ];
    }

    public function suspendAccount(string $username, string $reason = 'Billing'): void
    {
        $accountId = $this->resolveAccountId($username);
        $response = $this->request()->post($this->baseUrl."/accounts/{$accountId}/suspend", []);

        if (! $response->successful()) {
            throw new RuntimeException('Strata Hosting Panel suspend failed: '.$this->responseMessage($response));
        }
    }

    public function unsuspendAccount(string $username): void
    {
        $accountId = $this->resolveAccountId($username);
        $response = $this->request()->post($this->baseUrl."/accounts/{$accountId}/unsuspend", []);

        if (! $response->successful()) {
            throw new RuntimeException('Strata Hosting Panel unsuspend failed: '.$this->responseMessage($response));
        }
    }

    public function terminateAccount(string $username): void
    {
        $accountId = $this->resolveAccountId($username);
        $response = $this->request()->delete($this->baseUrl."/accounts/{$accountId}");

        if (! in_array($response->status(), [200, 202, 204], true)) {
            throw new RuntimeException('Strata Hosting Panel termination failed: '.$this->responseMessage($response));
        }
    }

    public function listAccounts(): array
    {
        $page = 1;
        $accounts = [];

        do {
            $response = $this->request()->get($this->baseUrl.'/accounts', [
                'per_page' => 100,
                'page' => $page,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Could not fetch Strata Hosting Panel accounts: '.$this->responseMessage($response));
            }

            $payload = $response->json();
            $data = $payload['data'] ?? [];
            $meta = $payload['meta'] ?? [];

            foreach ($data as $account) {
                $accounts[] = [
                    'remote_id' => $account['id'] ?? null,
                    'username' => $account['username'] ?? '',
                    'domain' => $account['primary_domain'] ?? $account['username'] ?? '',
                    'email' => $account['user']['email'] ?? '',
                    'plan' => $account['hosting_package']['name'] ?? '',
                    'suspended' => ($account['status'] ?? '') === 'suspended',
                ];
            }

            $page++;
            $lastPage = (int) ($meta['last_page'] ?? 1);
        } while ($page <= $lastPage);

        return $accounts;
    }

    public function listPackages(): array
    {
        $response = $this->request()->get($this->baseUrl.'/packages');

        if (! $response->successful()) {
            throw new RuntimeException('Could not fetch Strata Hosting Panel packages: '.$this->responseMessage($response));
        }

        return collect($response->json('data') ?? [])
            ->map(fn (array $package) => [
                'id' => $package['id'] ?? null,
                'slug' => $package['slug'] ?? null,
                'name' => $package['name'] ?? '',
                'disk_mb' => (int) ($package['limits']['disk_limit_mb'] ?? 0),
                'bandwidth_mb' => (int) ($package['limits']['bandwidth_limit_mb'] ?? 0),
                'max_domains' => (int) ($package['limits']['max_domains'] ?? 0),
                'max_email_accounts' => (int) ($package['limits']['max_email_accounts'] ?? 0),
                'max_databases' => (int) ($package['limits']['max_databases'] ?? 0),
                'max_ftp_accounts' => (int) ($package['limits']['max_ftp_accounts'] ?? 0),
                'php_version' => $package['php_version'] ?? null,
            ])
            ->all();
    }

    public function packageExists(string $name): bool
    {
        return collect($this->listPackages())->contains(function (array $package) use ($name) {
            return strcasecmp((string) $package['name'], $name) === 0
                || strcasecmp((string) ($package['slug'] ?? ''), $name) === 0;
        });
    }

    public function createPackage(string $name, array $config = []): void
    {
        throw new RuntimeException('Strata Hosting Panel package creation is not exposed through the API yet. Create hosting packages inside the panel and then sync them into billing.');
    }

    private function resolvePackageId(?string $plan): ?int
    {
        if (! $plan) {
            return null;
        }

        $package = collect($this->listPackages())->first(function (array $item) use ($plan) {
            return strcasecmp((string) $item['name'], $plan) === 0
                || strcasecmp((string) ($item['slug'] ?? ''), $plan) === 0;
        });

        if (! $package || empty($package['id'])) {
            throw new RuntimeException("Strata Hosting Panel package [{$plan}] was not found.");
        }

        return (int) $package['id'];
    }

    private function resolveAccountId(string $username): int
    {
        $match = $this->findAccount($username);

        if (! $match || empty($match['id'])) {
            throw new RuntimeException("Strata Hosting Panel account [{$username}] was not found.");
        }

        return (int) $match['id'];
    }

    private function findAccount(string $username): ?array
    {
        $response = $this->request()->get($this->baseUrl.'/accounts', [
            'search' => $username,
            'per_page' => 100,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Could not search Strata Hosting Panel accounts: '.$this->responseMessage($response));
        }

        return collect($response->json('data') ?? [])
            ->first(fn (array $account) => ($account['username'] ?? '') === $username);
    }

    private function waitForAccountReadiness(string $username, int $timeoutSeconds = 20): void
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            try {
                $account = $this->findAccount($username);

                if ($account && in_array((string) ($account['status'] ?? ''), ['active', 'suspended'], true)) {
                    return;
                }
            } catch (Throwable) {
                // A newly created account can take a few seconds to become queryable.
            }

            usleep(500_000);
        } while (microtime(true) < $deadline);

        if (! $this->findAccount($username)) {
            throw new RuntimeException("Strata Hosting Panel account [{$username}] was created but did not become queryable in time.");
        }
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->timeout(30)
            ->withOptions(['verify' => $this->verifyTls]);
    }

    private function responseMessage(Response $response): string
    {
        return $response->json('error')
            ?? $response->json('message')
            ?? $response->body()
            ?? (string) $response->status();
    }

    private function generateUsername(string $domain): string
    {
        $base = preg_replace('/\.[^.]+$/', '', $domain);
        $base = preg_replace('/[^a-z0-9]/', '', strtolower((string) $base));
        $base = substr($base, 0, 10);
        $base = $base !== '' ? $base : 'acct';

        return substr($base.Str::lower(Str::random(4)), 0, 16);
    }
}
