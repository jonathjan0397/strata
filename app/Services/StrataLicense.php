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

    /**
     * Activate a 14-day trial on the license server for this installation.
     * Returns ['success' => true] or ['error' => '...'].
     */
    public static function startTrial(): array
    {
        $serverUrl = config('strata.license_server_url');
        if (! $serverUrl) {
            return ['error' => 'License server not configured.'];
        }

        $lockPath = storage_path('installed.lock');
        if (! file_exists($lockPath)) {
            return ['error' => 'Installation lock file not found.'];
        }

        $lock          = json_decode(file_get_contents($lockPath), true) ?? [];
        $installToken  = $lock['install_token'] ?? null;
        $installSecret = $lock['install_secret'] ?? null;

        if (! $installToken || ! $installSecret) {
            return ['error' => 'Installation credentials missing.'];
        }

        try {
            $response = Http::timeout(8)
                ->post(rtrim($serverUrl, '/').'/api/trial', [
                    'install_token'  => $installToken,
                    'install_secret' => $installSecret,
                    'software'       => 'strata-billing',
                    'version'        => $lock['version'] ?? 'unknown',
                    'app_url'        => config('app.url'),
                ]);

            if (! $response->successful()) {
                return ['error' => $response->json('error') ?? 'Trial activation failed.'];
            }

            // Refresh cache so the new features take effect immediately.
            static::refresh();

            return ['success' => true];

        } catch (\Throwable) {
            return ['error' => 'Could not reach the license server.'];
        }
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

        // Always reconcile version from composer.json so FTP/manual deploys
        // report the correct version without requiring an upgrade-wizard run.
        $codeVersion = static::readComposerVersion();
        if ($codeVersion !== 'unknown' && ($lock['version'] ?? '') !== $codeVersion) {
            $lock['version'] = $codeVersion;
            file_put_contents($lockPath, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $version = $lock['version'] ?? 'unknown';
        $appUrl = config('app.url');

        try {
            $response = Http::timeout(8)
                ->post(rtrim($serverUrl, '/').'/api/ping', [
                    'install_token'  => $installToken,
                    'install_secret' => $installSecret,
                    'software'       => 'strata-billing',
                    'version'        => $version,
                    'app_url'        => $appUrl,
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
                'status'          => $body['status'] ?? 'active',
                'features'        => $body['features'] ?? [],
                'trial_used'      => $body['trial_used'] ?? false,
                'expires_in_days' => $body['expires_in_days'] ?? null,
                'synced_at'       => now()->toIso8601String(),
            ];

        } catch (\Throwable) {
            return $default;
        }
    }

    /** Read the version string from composer.json without loading the full file. */
    private static function readComposerVersion(): string
    {
        $path = base_path('composer.json');
        if (! is_file($path)) {
            return 'unknown';
        }

        $decoded = json_decode(file_get_contents($path), true);

        return $decoded['version'] ?? 'unknown';
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
