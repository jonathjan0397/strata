<?php

namespace App\Gateways;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Authorize.Net AIM (Advanced Integration Method) gateway driver.
 *
 * Credentials required in .env:
 *   AUTHORIZENET_API_LOGIN_ID=
 *   AUTHORIZENET_TRANSACTION_KEY=
 *   AUTHORIZENET_SANDBOX=true   (set false for production)
 */
class AuthorizeNetGateway implements PaymentGateway
{
    private string $loginId;

    private string $transactionKey;

    private string $endpoint;

    public function __construct()
    {
        $this->loginId = config('services.authorizenet.login_id', '');
        $this->transactionKey = config('services.authorizenet.transaction_key', '');
        $this->endpoint = config('services.authorizenet.sandbox', true)
            ? 'https://apitest.authorize.net/xml/v1/request.api'
            : 'https://api.authorize.net/xml/v1/request.api';
    }

    public function slug(): string
    {
        return 'authorizenet';
    }

    public function supportsTokens(): bool
    {
        return true; // Supports Customer Payment Profiles
    }

    /**
     * Charge using an opaque data value (from Accept.js) or a stored profile.
     *
     * Expected $options keys:
     *   - opaque_descriptor  (from Accept.js — used for new cards)
     *   - opaque_value       (from Accept.js — used for new cards)
     *   - customer_profile_id     (stored profile ID)
     *   - customer_payment_id     (stored payment profile ID)
     */
    public function charge(float $amount, string $currency, array $options = []): array
    {
        if (isset($options['opaque_value'])) {
            $paymentNode = [
                'opaqueData' => [
                    'dataDescriptor' => $options['opaque_descriptor'] ?? 'COMMON.ACCEPT.INAPP.PAYMENT',
                    'dataValue' => $options['opaque_value'],
                ],
            ];
        } elseif (isset($options['customer_profile_id'])) {
            $paymentNode = [
                'profile' => [
                    'customerProfileId' => $options['customer_profile_id'],
                    'customerPaymentProfileId' => $options['customer_payment_id'],
                ],
            ];
        } else {
            throw new RuntimeException('AuthorizeNetGateway::charge requires opaque_value or customer_profile_id');
        }

        $payload = [
            'createTransactionRequest' => [
                'merchantAuthentication' => $this->auth(),
                'transactionRequest' => array_merge([
                    'transactionType' => 'authCaptureTransaction',
                    'amount' => number_format($amount, 2, '.', ''),
                    'payment' => $paymentNode,
                ], $options['extra'] ?? []),
            ],
        ];

        $response = $this->post($payload);

        $result = $response['transactionResponse'] ?? [];
        $status = $result['responseCode'] ?? null;

        if ($status !== '1') {
            $message = $result['errors'][0]['errorText'] ?? ($result['messages'][0]['description'] ?? 'Transaction failed');
            throw new RuntimeException("Authorize.Net charge failed: {$message}");
        }

        return [
            'id' => $result['transId'] ?? '',
            'status' => 'succeeded',
            'raw' => $result,
        ];
    }

    /**
     * Refund a transaction.
     *
     * $options must include:
     *   - card_last4   (last 4 digits of the card used)
     *   - expiry       (MM/YY)
     */
    public function refund(string $transactionId, ?float $amount = null): array
    {
        $payload = [
            'createTransactionRequest' => [
                'merchantAuthentication' => $this->auth(),
                'transactionRequest' => [
                    'transactionType' => 'refundTransaction',
                    'amount' => $amount ? number_format($amount, 2, '.', '') : null,
                    'payment' => [
                        'creditCard' => [
                            'cardNumber' => '0000', // Authorize.Net requires last 4 for refunds
                            'expirationDate' => 'XXXX',
                        ],
                    ],
                    'refTransId' => $transactionId,
                ],
            ],
        ];

        $response = $this->post($payload);
        $result = $response['transactionResponse'] ?? [];

        if (($result['responseCode'] ?? null) !== '1') {
            $message = $result['errors'][0]['errorText'] ?? 'Refund failed';
            throw new RuntimeException("Authorize.Net refund failed: {$message}");
        }

        return [
            'id' => $result['transId'] ?? '',
            'status' => 'refunded',
            'raw' => $result,
        ];
    }

    private function auth(): array
    {
        return [
            'name' => $this->loginId,
            'transactionKey' => $this->transactionKey,
        ];
    }

    private function post(array $payload): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->endpoint, $payload);

        if (! $response->successful()) {
            throw new RuntimeException("Authorize.Net HTTP error: {$response->status()}");
        }

        // Strip BOM if present (Authorize.Net sometimes returns one)
        $body = ltrim($response->body(), "\xEF\xBB\xBF");

        return json_decode($body, true) ?? [];
    }
}
