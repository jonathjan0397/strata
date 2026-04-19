<?php

namespace App\Services;

use App\Licensing\Features;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StrataLicense
{
    private const CACHE_KEY = 'strata_license_response';
    private const CACHE_TTL = 172800;
    private const ATTEMPT_CACHE_KEY = 'strata_license_last_attempt_at';
    private const LOCK_CACHE_KEY = 'strata_license_sync_lock';
    private const APPLICATION_NAME = 'strata-billing-platform';
    private const PING_TIMEOUT = 15;
    private const AUTO_SYNC_INTERVAL_HOURS = 3;
    private const RETRY_COOLDOWN_SECONDS = 900;

    public static function sync(): array
    {
        $url = config('strata.license_server_url');

        if (! $url) {
            $fallback = self::fallback();
            Cache::put(self::CACHE_KEY, $fallback, self::CACHE_TTL);

            return $fallback;
        }

        try {
            $response = Http::timeout(self::PING_TIMEOUT)
                ->acceptJson()
                ->post(rtrim($url, '/') . '/api/ping', [
                    'application_name' => self::APPLICATION_NAME,
                    'admin_email' => self::resolveAdminEmail(),
                    'version' => self::version(),
                    'features' => Features::enabledKeys(),
                    'app_url' => config('app.url'),
                    'app_info' => self::buildAppInfo(),
                ]);

            if (! $response->successful()) {
                Log::warning('StrataLicense: ping returned HTTP ' . $response->status());

                return self::useCachedOrFallback();
            }

            $data = $response->json();

            if (! is_array($data) || ! isset($data['status'])) {
                Log::warning('StrataLicense: unexpected response format');

                return self::useCachedOrFallback();
            }

            $cached = [
                'status' => in_array($data['status'] ?? null, ['active', 'inactive'], true)
                    ? $data['status']
                    : 'inactive',
                'features' => self::normalizeFeatures($data['features'] ?? []),
                'messages' => self::normalizeMessages($data['messages'] ?? []),
                'synced_at' => now()->toIso8601String(),
            ];

            Cache::put(self::CACHE_KEY, $cached, self::CACHE_TTL);

            return $cached;
        } catch (Throwable $e) {
            Log::warning('StrataLicense: sync failed - ' . $e->getMessage());

            return self::useCachedOrFallback();
        }
    }

    public static function refresh(): array
    {
        return self::sync();
    }

    public static function ensureCurrent(bool $force = false): array
    {
        if (! self::isManaged()) {
            return self::fallback();
        }

        if (! $force && ! self::needsSync()) {
            return self::cached();
        }

        if (! $force && ! self::retryWindowElapsed()) {
            return self::cached();
        }

        if (! Cache::add(self::LOCK_CACHE_KEY, true, 60)) {
            return self::cached();
        }

        try {
            Cache::forever(self::ATTEMPT_CACHE_KEY, now()->toIso8601String());

            return self::sync();
        } finally {
            Cache::forget(self::LOCK_CACHE_KEY);
        }
    }

    public static function status(): string
    {
        return self::cached()['status'] ?? 'inactive';
    }

    public static function hasFeature(string $key): bool
    {
        foreach (self::approvedFeatures() as $feature) {
            if (($feature['key'] ?? null) !== $key) {
                continue;
            }

            $expiresAt = $feature['expires_at'] ?? null;

            return ! $expiresAt || now()->toDateString() <= $expiresAt;
        }

        return false;
    }

    public static function features(): array
    {
        return array_values(array_map(
            static fn (array $feature): string => $feature['key'],
            self::approvedFeatures(),
        ));
    }

    public static function approvedFeatures(): array
    {
        return self::cached()['features'] ?? [];
    }

    public static function messages(?string $type = null): array
    {
        $messages = self::cached()['messages'] ?? [];

        if ($type === null) {
            return $messages;
        }

        return array_values(array_filter(
            $messages,
            static fn (array $message): bool => ($message['type'] ?? null) === $type
        ));
    }

    public static function cached(): array
    {
        return Cache::get(self::CACHE_KEY, self::fallback());
    }

    public static function isManaged(): bool
    {
        return (bool) config('strata.license_server_url');
    }

    public static function isFreshInstall(): bool
    {
        return self::isManaged() && (self::cached()['synced_at'] ?? null) === null;
    }

    public static function isStale(int $hours = 48): bool
    {
        $syncedAt = self::cached()['synced_at'] ?? null;

        if (! $syncedAt) {
            return false;
        }

        try {
            return now()->diffInHours($syncedAt) >= $hours;
        } catch (Throwable) {
            return false;
        }
    }

    public static function needsSync(int $hours = self::AUTO_SYNC_INTERVAL_HOURS): bool
    {
        $syncedAt = self::cached()['synced_at'] ?? null;

        if (! $syncedAt) {
            return true;
        }

        try {
            return now()->diffInHours(Carbon::parse($syncedAt)) >= $hours;
        } catch (Throwable) {
            return true;
        }
    }

    private static function useCachedOrFallback(): array
    {
        if (Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        return self::fallback();
    }

    private static function fallback(): array
    {
        return [
            'status' => self::isManaged() ? 'inactive' : 'active',
            'features' => [],
            'messages' => [],
            'synced_at' => null,
        ];
    }

    private static function retryWindowElapsed(): bool
    {
        $attemptedAt = Cache::get(self::ATTEMPT_CACHE_KEY);

        if (! $attemptedAt) {
            return true;
        }

        try {
            return Carbon::parse($attemptedAt)->diffInSeconds(now()) >= self::RETRY_COOLDOWN_SECONDS;
        } catch (Throwable) {
            return true;
        }
    }

    private static function resolveAdminEmail(): string
    {
        return (string) (User::role(['super-admin', 'admin'])
            ->orderBy('id')
            ->value('email') ?? '');
    }

    private static function buildAppInfo(): string
    {
        $parts = array_filter([
            self::cpuSummary(),
            self::memorySummary(),
            self::diskSummary(),
        ]);

        return implode(' | ', $parts);
    }

    private static function cpuSummary(): string
    {
        $coreCount = self::cpuCoreCount();
        $model = self::cpuModel();

        if ($coreCount === null && $model === null) {
            return 'Processors: unavailable';
        }

        if ($model !== null && $coreCount !== null) {
            return sprintf('Processors: %d x %s', $coreCount, $model);
        }

        return sprintf('Processors: %s', $model ?? (string) $coreCount);
    }

    private static function memorySummary(): string
    {
        $memInfo = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $values = [];

        foreach ($memInfo as $line) {
            if (preg_match('/^(MemTotal|MemAvailable):\s+(\d+)\s+kB$/', $line, $matches)) {
                $values[$matches[1]] = (int) $matches[2] * 1024;
            }
        }

        if (! isset($values['MemTotal'])) {
            return 'RAM: unavailable';
        }

        $summary = 'RAM: ' . self::formatBytes($values['MemTotal']) . ' total';

        if (isset($values['MemAvailable'])) {
            $summary .= ', ' . self::formatBytes($values['MemAvailable']) . ' free';
        }

        return $summary;
    }

    private static function diskSummary(): string
    {
        $mounts = self::diskMounts();

        if ($mounts === []) {
            $path = base_path();
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);

            if ($free === false || $total === false) {
                return 'Disks: unavailable';
            }

            return sprintf(
                'Disks: %s free / %s total at %s',
                self::formatBytes((float) $free),
                self::formatBytes((float) $total),
                $path,
            );
        }

        $parts = [];

        foreach ($mounts as $mount) {
            $free = @disk_free_space($mount);
            $total = @disk_total_space($mount);

            if ($free === false || $total === false) {
                continue;
            }

            $parts[] = sprintf(
                '%s %s free / %s total',
                $mount,
                self::formatBytes((float) $free),
                self::formatBytes((float) $total),
            );
        }

        return $parts === [] ? 'Disks: unavailable' : 'Disks: ' . implode('; ', $parts);
    }

    private static function version(): string
    {
        $lockPath = storage_path('installed.lock');
        if (is_file($lockPath)) {
            $lock = json_decode(file_get_contents($lockPath), true) ?: [];
            if (! empty($lock['version'])) {
                return (string) $lock['version'];
            }
        }

        $composerPath = base_path('composer.json');
        if (is_file($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true) ?: [];
            if (! empty($composer['version'])) {
                return (string) $composer['version'];
            }
        }

        return 'unknown';
    }

    private static function normalizeFeatures(mixed $features): array
    {
        if (! is_array($features)) {
            return [];
        }

        $normalized = [];

        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $key = isset($feature['key']) ? trim((string) $feature['key']) : '';
            $expiresAt = self::normalizeDate($feature['expires_at'] ?? null);

            if ($key === '' || ! preg_match('/^[a-z0-9_]+$/', $key)) {
                continue;
            }

            $normalized[] = [
                'key' => $key,
                'expires_at' => $expiresAt,
            ];
        }

        return array_values($normalized);
    }

    private static function normalizeMessages(mixed $messages): array
    {
        if (! is_array($messages)) {
            return [];
        }

        $normalized = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $type = isset($message['type']) ? trim((string) $message['type']) : '';
            $body = isset($message['body']) ? trim((string) $message['body']) : '';

            if (! in_array($type, ['security', 'update', 'info'], true) || $body === '') {
                continue;
            }

            $normalized[] = [
                'type' => $type,
                'body' => Str::limit($body, 2000, ''),
                'expires_at' => self::normalizeDate($message['expires_at'] ?? null),
            ];
        }

        return array_values($normalized);
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private static function cpuCoreCount(): ?int
    {
        $cpuInfo = @file('/proc/cpuinfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $count = 0;

        foreach ($cpuInfo as $line) {
            if (str_starts_with($line, 'processor')) {
                $count++;
            }
        }

        return $count > 0 ? $count : null;
    }

    private static function cpuModel(): ?string
    {
        $cpuInfo = @file('/proc/cpuinfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($cpuInfo as $line) {
            if (! str_starts_with($line, 'model name')) {
                continue;
            }

            [, $value] = array_pad(explode(':', $line, 2), 2, null);

            return $value ? trim($value) : null;
        }

        return php_uname('m') ?: null;
    }

    private static function diskMounts(): array
    {
        $mountInfo = @file('/proc/mounts', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $ignoredTypes = [
            'proc',
            'sysfs',
            'tmpfs',
            'devtmpfs',
            'devpts',
            'securityfs',
            'cgroup',
            'cgroup2',
            'pstore',
            'autofs',
            'mqueue',
            'hugetlbfs',
            'debugfs',
            'tracefs',
            'configfs',
            'fusectl',
            'overlay',
            'squashfs',
            'rpc_pipefs',
            'binfmt_misc',
            'nsfs',
        ];

        $mounts = [];

        foreach ($mountInfo as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (! is_array($parts) || count($parts) < 3) {
                continue;
            }

            [$device, $mountPoint, $fsType] = [$parts[0], $parts[1], $parts[2]];
            $mountPoint = str_replace('\\040', ' ', $mountPoint);

            if (in_array($fsType, $ignoredTypes, true)) {
                continue;
            }

            if (! str_starts_with($device, '/dev/')) {
                continue;
            }

            $mounts[$mountPoint] = true;
        }

        $paths = array_keys($mounts);
        usort($paths, static function (string $a, string $b): int {
            if ($a === '/') {
                return -1;
            }

            if ($b === '/') {
                return 1;
            }

            return strcmp($a, $b);
        });

        return $paths;
    }

    private static function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return number_format($bytes, $index === 0 ? 0 : 1) . ' ' . $units[$index];
    }
}
