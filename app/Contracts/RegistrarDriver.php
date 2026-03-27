<?php

namespace App\Contracts;

interface RegistrarDriver
{
    /**
     * Check if a domain name is available for registration.
     *
     * @return array{available: bool, price?: float, currency?: string}
     */
    public function checkAvailability(string $domain): array;

    /**
     * Register a domain.
     *
     * @param  array{
     *   registrant_first: string,
     *   registrant_last: string,
     *   registrant_email: string,
     *   registrant_phone: string,
     *   registrant_address: string,
     *   registrant_city: string,
     *   registrant_state: string,
     *   registrant_zip: string,
     *   registrant_country: string,
     *   nameservers?: string[],
     * }  $contact
     * @return array{success: bool, registrar_data: array}
     */
    public function registerDomain(string $domain, int $years, array $contact): array;

    /**
     * Renew a domain.
     *
     * @return array{success: bool, expires_at: string}
     */
    public function renewDomain(string $domain, int $years): array;

    /**
     * Initiate a domain transfer.
     *
     * @return array{success: bool, transfer_id: string}
     */
    public function transferDomain(string $domain, string $authCode): array;

    /**
     * Get current nameservers for a domain.
     *
     * @return string[]
     */
    public function getNameservers(string $domain): array;

    /**
     * Set nameservers for a domain.
     *
     * @param  string[]  $nameservers
     */
    public function setNameservers(string $domain, array $nameservers): void;

    /**
     * Get domain info (expiry, lock, privacy, etc.).
     *
     * @return array{expires_at: string, locked: bool, privacy: bool, nameservers: string[]}
     */
    public function getInfo(string $domain): array;

    /**
     * Set registrar lock (transfer lock) on a domain.
     */
    public function setLock(string $domain, bool $locked): void;

    /**
     * Enable or disable WHOIS privacy on a domain.
     */
    public function setPrivacy(string $domain, bool $enabled): void;

    /** Driver slug used to identify this registrar in the DB. */
    public function slug(): string;
}
