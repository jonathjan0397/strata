<?php

namespace App\Contracts;

interface PaymentGateway
{
    /**
     * Charge a one-time payment.
     *
     * @param  float  $amount  Amount in dollars (not cents)
     * @param  string  $currency  ISO 4217 currency code (e.g. "usd")
     * @param  array  $options  Gateway-specific options (token, card nonce, etc.)
     * @return array{id: string, status: string, raw: mixed}
     */
    public function charge(float $amount, string $currency, array $options = []): array;

    /**
     * Refund a previously captured charge.
     *
     * @param  string  $transactionId  Gateway transaction/payment ID
     * @param  float|null  $amount  Partial refund amount; null = full refund
     * @return array{id: string, status: string, raw: mixed}
     */
    public function refund(string $transactionId, ?float $amount = null): array;

    /**
     * Whether this gateway supports stored (tokenized) payment methods.
     */
    public function supportsTokens(): bool;

    /**
     * Return the gateway's machine-readable slug (e.g. "stripe", "paypal", "authorizenet").
     */
    public function slug(): string;
}
