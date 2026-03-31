<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Manages the cached license/telemetry state for this installation.
 *
 * All public methods are safe to call from anywhere — if the license
 * server is unreachable or not configured, they degrade gracefully and
 * never throw exceptions visible to end users.
 */
class StrataLicense
{
    private const CACHE_KEY = 'strata_license_payload';

    private const CACHE_TTL = 25 * 60 * 60; // 25 hours

    // ── Public API ────────────────────────────────────────────────────────────

    /** Returns true if this install is considered active/licensed. */
    public static function active(): bool
    {
        return (static::payload()['status'] ?? 'active') === 'active';
    }

    /** Returns true if a named premium feature is enabled for this install. */
    public static function hasFeature(string $feature): bool
    {
        return in_array($feature, static::payload()['features'] ?? [], true);
    }

    /**
     * Returns the raw payload cached from the last successful ping.
     * Falls back to a permissive default so installations never break.
     */
    public static function payload(): array
    {
        return Cache::remember(static::CACHE_KEY, static::CACHE_TTL, function () {
            return static::fetch();
        });
    }

    /** Force an immediate re-ping and cache refresh. */
    public static function refresh(): array
    {
        Cache::forget(static::CACHE_KEY);
        $payload = static::fetch();
        Cache::put(static::CACHE_KEY, $payload, static::CACHE_TTL);

        return $payload;
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Sends the ping to the license server and verifies the HMAC signature.
     * Returns a permissive default on any failure.
     */
    public static function fetch(): array
    {
        $default = ['status' => 'active', 'features' => []];

        $serverUrl = config('strata.license_server_url');
        if (! $serverUrl) {
            return $default;
        }

        $lockPath = storage_path('installed.lock');
        if (! file_exists($lockPath)) {
            return $default;
        }

        $lock = json_decode(file_get_contents($lockPath), true) ?? [];
        $installToken = $lock['install_token'] ?? null;
        $installSecret = $lock['install_secret'] ?? null;

        if (! $installToken || ! $installSecret) {
            return $default;
        }

        $version = $lock['version'] ?? 'unknown';
        $appUrl = config('app.url');

        try {
            $response = Http::timeout(8)
                ->post(rtrim($serverUrl, '/').'/api/ping', [
                    'install_token' => $installToken,
                    'install_secret' => $installSecret,
                    'version' => $version,
                    'app_url' => $appUrl,
                ]);

            if (! $response->successful()) {
                return $default;
            }

            $body = $response->json();

            // If server returned no sig (e.g. secret not yet registered), trust permissive default
            if (empty($body['sig'])) {
                return $default;
            }

            if (! static::verifySignature($body, $installSecret)) {
                return $default;
            }

            unset($body['sig']);

            return [
                'status' => $body['status'] ?? 'active',
                'features' => $body['features'] ?? [],
            ];

        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Verify the HMAC-SHA256 signature returned by the license server.
     * Server signs json_encode($payload) before appending 'sig'.
     */
    private static function verifySignature(array $body, string $secret): bool
    {
        $sig = $body['sig'] ?? null;
        if (! $sig) {
            return false;
        }

        $payload = $body;
        unset($payload['sig']);

        $expected = hash_hmac(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $secret
        );

        return hash_equals($expected, (string) $sig);
    }
}
