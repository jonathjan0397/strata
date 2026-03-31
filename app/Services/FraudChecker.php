<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FraudChecker
{
    /**
     * Evaluate an order for fraud risk using the configured provider (MaxMind minFraud).
     *
     * Returns an array with:
     *   - score   float|null  — risk score 0–100, null if check was skipped or failed
     *   - flags   array       — array of warning strings (reserved for future use)
     *   - blocked bool        — true only when score exceeds threshold AND action = 'reject'
     */
    public static function evaluate(Request $request, string $email, float $orderTotal): array
    {
        $empty = ['score' => null, 'flags' => [], 'blocked' => false];

        if (! Setting::get('fraud_check_enabled')) {
            return $empty;
        }

        $accountId = Setting::get('fraud_maxmind_account_id');
        $licenseKey = Setting::get('fraud_maxmind_license_key');

        if (! $accountId || ! $licenseKey) {
            return $empty;
        }

        try {
            $response = Http::withBasicAuth($accountId, $licenseKey)
                ->timeout(5)
                ->acceptJson()
                ->post('https://minfraud.maxmind.com/minfraud/v2.0/score', [
                    'device' => ['ip_address' => $request->ip()],
                    'email' => ['address' => $email],
                    'order' => ['amount' => $orderTotal, 'currency' => 'USD'],
                ]);

            if (! $response->successful()) {
                Log::warning('FraudChecker: minFraud API returned '.$response->status(), [
                    'body' => $response->body(),
                ]);

                return $empty;
            }

            $score = (float) ($response->json('risk_score') ?? 0);
            $threshold = (int) Setting::get('fraud_score_threshold', 75);
            $action = Setting::get('fraud_action', 'flag');

            return [
                'score' => $score,
                'flags' => [],
                'blocked' => ($score >= $threshold && $action === 'reject'),
            ];
        } catch (\Throwable $e) {
            Log::warning('FraudChecker: exception during API call — '.$e->getMessage());

            return $empty;
        }
    }
}
