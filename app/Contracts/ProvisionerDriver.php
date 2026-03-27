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
}
