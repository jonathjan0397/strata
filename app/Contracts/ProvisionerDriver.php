<?php

namespace App\Contracts;

interface ProvisionerDriver
{
    /**
     * Create a hosting account.
     *
     * @return array{username: string, password: string, domain: string}
     */
    public function createAccount(string $domain, ?string $plan = null): array;

    /** Suspend an account. */
    public function suspendAccount(string $username, string $reason = 'Billing'): void;

    /** Unsuspend / reactivate an account. */
    public function unsuspendAccount(string $username): void;

    /** Permanently remove an account. */
    public function terminateAccount(string $username): void;

    /** Driver slug — matches Module::type. */
    public function slug(): string;

    /**
     * List all hosting accounts on the server.
     *
     * @return array<int, array{username: string, domain: string, email: string, plan: string, suspended: bool}>
     */
    public function listAccounts(): array;

    /**
     * List all packages/plans defined on the server.
     *
     * @return array<int, array{name: string, disk_mb: int, bandwidth_mb: int}>
     */
    public function listPackages(): array;

    /**
     * Return true if a package with this name already exists on the server.
     */
    public function packageExists(string $name): bool;

    /**
     * Create a hosting package/plan on the server.
     *
     * @param  array{disk_mb?: int, bandwidth_mb?: int}  $config
     */
    public function createPackage(string $name, array $config = []): void;
}
