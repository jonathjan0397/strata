<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StrataLicense;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;
use ZipArchive;

class UpgradeController extends Controller
{
    private const AUTH_TOKEN_PREFIX = 'upgrade_auth_token:';
    private const AUTH_TOKEN_TTL_SECONDS = 900;

    public function index(): Response
    {
        abort_unless(file_exists(storage_path('installed.lock')), 404, 'Strata Service Billing and Support Platform is not installed.');

        return Inertia::render('Install/Upgrade', [
            'currentVersion' => null,
            'codeVersion' => null,
            'alreadyUpdated' => false,
            'hasZipExtension' => false,
            'checks' => [],
            'hardFail' => false,
            'installedAt' => null,
            'lastUpgradedAt' => null,
            'updateRepo' => null,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $email = trim((string) $request->input('email', ''));
        $password = base64_decode((string) $request->input('password', ''), true);

        if (! $email || ! $password) {
            return response()->json(['valid' => false, 'error' => 'Email and password are required.'], 422);
        }

        try {
            $user = $this->resolveSuperAdmin($email);

            if (! $user || ! Hash::check($password, $user->password)) {
                return response()->json(['valid' => false, 'error' => 'Invalid credentials or account is not a super-admin.'], 401);
            }

            $token = $this->issueAuthToken($user->id);

            return response()->json([
                'valid' => true,
                'auth_token' => $token,
                'context' => $this->upgradeContext(),
            ]);
        } catch (Throwable $e) {
            return response()->json(['valid' => false, 'error' => 'Database error: '.$e->getMessage()], 500);
        }
    }

    public function release(Request $request): JsonResponse
    {
        try {
            $this->authorizeRequest($request);
            $release = $this->fetchLatestRelease();

            return response()->json([
                'success' => true,
                'release' => $release,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function peekZip(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        if (! $request->hasFile('zip') || ! $request->file('zip')->isValid()) {
            return response()->json(['success' => false, 'error' => 'No ZIP uploaded.'], 422);
        }

        if (! extension_loaded('zip')) {
            return response()->json(['success' => false, 'error' => 'PHP ext-zip not available.'], 500);
        }

        try {
            $metadata = $this->inspectZip($request->file('zip')->getRealPath());

            return response()->json([
                'success' => true,
                'version' => $metadata['version'],
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function run(Request $request): JsonResponse
    {
        @set_time_limit(0);

        try {
            $user = $this->authorizeRequest($request);

            if (! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Auth error: '.$e->getMessage()], 500);
        }

        $log = [];
        $tempZipPath = null;

        try {
            $downloadLatest = $request->boolean('download_latest');
            $skipExtract = $request->boolean('skip_extract');

            if (! $skipExtract) {
                if (! extension_loaded('zip')) {
                    throw new RuntimeException('PHP ext-zip is not available. Upload files via FTP instead and re-run without package extraction.');
                }

                if ($downloadLatest) {
                    $release = $this->fetchLatestRelease();
                    $tempZipPath = $this->downloadReleasePackage($release);
                    $zipPath = $tempZipPath;
                    $log[] = "Downloaded {$release['tag']} from {$release['source']}.";
                } elseif ($request->hasFile('zip') && $request->file('zip')->isValid()) {
                    $zipPath = $request->file('zip')->getRealPath();
                    $log[] = 'Using uploaded release package.';
                } else {
                    throw new RuntimeException('No upgrade package was provided.');
                }

                $metadata = $this->inspectZip($zipPath);
                $count = $this->extractZip($zipPath, $metadata['root']);
                $log[] = "Extracted {$count} files for {$metadata['version']}.";
            } else {
                $log[] = 'File extraction skipped - using already-uploaded code.';
            }

            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
            $migrateOutput = trim(Artisan::output());
            $log[] = 'Migrations: '.($migrateOutput ?: 'Nothing to migrate.');

            foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear', 'event:clear'] as $command) {
                try {
                    Artisan::call($command);
                } catch (Throwable) {
                }
            }

            try {
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                $log[] = 'Config and route caches rebuilt.';
            } catch (Throwable) {
                $log[] = 'Cache rebuild skipped (non-fatal).';
            }

            $lockPath = storage_path('installed.lock');
            $lock = $this->readLock();
            $newVersion = $this->appVersion();

            $lock['version'] = $newVersion;
            $lock['upgraded_at'] = now()->toIso8601String();

            if (empty($lock['install_token'])) {
                $lock['install_token'] = Str::uuid()->toString();
            }

            if (empty($lock['install_secret'])) {
                $lock['install_secret'] = bin2hex(random_bytes(32));
            }

            file_put_contents($lockPath, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $log[] = "Lock file updated to {$newVersion}.";

            try {
                $sync = StrataLicense::sync();
                $log[] = 'License ping completed with status '.($sync['status'] ?? 'unknown').'.';
            } catch (Throwable $e) {
                $log[] = 'License ping skipped after upgrade: '.$e->getMessage();
            }

            return response()->json([
                'success' => true,
                'new_version' => $newVersion,
                'log' => $log,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'log' => $log,
            ], 500);
        } finally {
            if ($tempZipPath && is_file($tempZipPath)) {
                @unlink($tempZipPath);
            }
        }
    }

    private function upgradeContext(): array
    {
        $lock = $this->readLock();
        $currentVersion = $lock['version'] ?? 'unknown';
        $codeVersion = $this->appVersion();

        $checks = [
            [
                'label' => 'PHP >= 8.3',
                'pass' => version_compare(PHP_VERSION, '8.3.0', '>='),
                'detail' => PHP_VERSION,
            ],
            [
                'label' => 'PHP ext-zip (file extraction)',
                'pass' => extension_loaded('zip'),
                'detail' => extension_loaded('zip') ? 'available' : 'missing - upload files via FTP and use "files already uploaded" mode',
                'warn' => true,
            ],
            [
                'label' => 'App root writable',
                'pass' => is_writable(base_path()),
                'detail' => base_path(),
            ],
            [
                'label' => 'Storage writable',
                'pass' => is_writable(storage_path()),
                'detail' => storage_path(),
            ],
            [
                'label' => 'Bootstrap cache writable',
                'pass' => is_writable(base_path('bootstrap/cache')),
                'detail' => base_path('bootstrap/cache'),
            ],
        ];

        $hardFail = collect($checks)->contains(
            fn ($check) => ! $check['pass'] && empty($check['warn'])
        );

        return [
            'currentVersion' => $currentVersion,
            'codeVersion' => $codeVersion,
            'alreadyUpdated' => $currentVersion !== $codeVersion,
            'hasZipExtension' => extension_loaded('zip'),
            'checks' => $checks,
            'hardFail' => $hardFail,
            'installedAt' => $lock['installed_at'] ?? null,
            'lastUpgradedAt' => $lock['upgraded_at'] ?? null,
            'updateRepo' => config('strata.update_repo'),
        ];
    }

    private function issueAuthToken(int $userId): string
    {
        $token = Str::random(64);

        Cache::put(
            self::AUTH_TOKEN_PREFIX.$token,
            ['user_id' => $userId],
            now()->addSeconds(self::AUTH_TOKEN_TTL_SECONDS)
        );

        return $token;
    }

    private function authorizeRequest(Request $request): User
    {
        $token = trim((string) $request->input('auth_token', ''));

        if ($token === '') {
            abort(response()->json(['success' => false, 'error' => 'Upgrade authorization is required.'], 401));
        }

        $payload = Cache::get(self::AUTH_TOKEN_PREFIX.$token);
        $userId = $payload['user_id'] ?? null;

        if (! is_int($userId) && ! ctype_digit((string) $userId)) {
            abort(response()->json(['success' => false, 'error' => 'Upgrade authorization has expired.'], 401));
        }

        $user = User::whereKey((int) $userId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
            ->first();

        if (! $user) {
            abort(response()->json(['success' => false, 'error' => 'Upgrade authorization is no longer valid.'], 401));
        }

        Cache::put(
            self::AUTH_TOKEN_PREFIX.$token,
            ['user_id' => $user->id],
            now()->addSeconds(self::AUTH_TOKEN_TTL_SECONDS)
        );

        return $user;
    }

    private function resolveSuperAdmin(string $email): ?User
    {
        return User::where('email', $email)
            ->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
            ->first();
    }

    private function fetchLatestRelease(): array
    {
        $repos = array_values(array_filter(array_unique(array_merge(
            [(string) config('strata.update_repo')],
            (array) config('strata.update_repo_fallbacks', [])
        ))));

        if ($repos === []) {
            throw new RuntimeException('No update repository is configured.');
        }

        $client = $this->githubClient(20)
            ->acceptJson()
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
            ]);

        $notFoundRepos = [];

        foreach ($repos as $repo) {
            try {
                $response = $client->get("https://api.github.com/repos/{$repo}/releases/latest")->throw();
                $payload = $response->json();
                $tag = (string) ($payload['tag_name'] ?? '');
                $asset = collect($payload['assets'] ?? [])
                    ->first(fn ($item) => str_ends_with(strtolower((string) ($item['name'] ?? '')), '.zip'));

                $downloadUrl = $asset['browser_download_url'] ?? $payload['zipball_url'] ?? null;
                $source = $asset ? 'release asset' : 'github source archive';

                if (! $tag || ! $downloadUrl) {
                    throw new RuntimeException("The latest release in {$repo} does not include a downloadable ZIP package.");
                }

                return [
                    'repo' => $repo,
                    'tag' => $tag,
                    'name' => $payload['name'] ?: $tag,
                    'published_at' => $payload['published_at'] ?? null,
                    'html_url' => $payload['html_url'] ?? null,
                    'notes' => $payload['body'] ?? '',
                    'download_url' => $downloadUrl,
                    'source' => $source,
                    'asset_name' => $asset['name'] ?? null,
                    'up_to_date' => version_compare(ltrim($this->appVersion(), 'v'), ltrim($tag, 'v'), '>='),
                ];
            } catch (RequestException $e) {
                $status = $e->response?->status();
                if ($status === 404) {
                    $notFoundRepos[] = $repo;
                    continue;
                }

                if ($status === 403) {
                    throw new RuntimeException('GitHub rate limit reached while checking for updates.');
                }

                throw new RuntimeException('Failed to check GitHub releases: '.$e->getMessage());
            }
        }

        throw new RuntimeException(
            'No published release was found for the configured update repositories: '.implode(', ', $notFoundRepos)
        );
    }

    private function downloadReleasePackage(array $release): string
    {
        $url = (string) ($release['download_url'] ?? '');

        if ($url === '') {
            throw new RuntimeException('No download URL is available for the selected release.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'strata-upgrade-');
        if ($tempPath === false) {
            throw new RuntimeException('Could not allocate a temporary file for the downloaded release.');
        }

        $zipPath = $tempPath.'.zip';
        @rename($tempPath, $zipPath);

        $client = $this->githubClient(120);

        try {
            $response = $client->withOptions(['sink' => $zipPath])->get($url)->throw();
            unset($response);
        } catch (Throwable $e) {
            @unlink($zipPath);
            throw new RuntimeException('Failed to download the release package: '.$e->getMessage());
        }

        if (! is_file($zipPath) || filesize($zipPath) === 0) {
            @unlink($zipPath);
            throw new RuntimeException('The downloaded release package is empty.');
        }

        return $zipPath;
    }

    private function githubClient(int $timeout)
    {
        $client = Http::timeout($timeout)
            ->withHeaders([
                'User-Agent' => 'strata-billing-support-platform-updater',
            ]);

        if (! config('strata.update_verify_tls', true)) {
            $client = $client->withoutVerifying();
        }

        if ($token = env('GITHUB_TOKEN')) {
            $client = $client->withToken($token);
        }

        return $client;
    }

    private function inspectZip(string $zipPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Invalid or corrupted ZIP file.');
        }

        try {
            $composerEntry = null;
            $root = '';

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name)) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $name);
                if (str_ends_with($normalized, '/composer.json')) {
                    $composerEntry = $normalized;
                    $root = substr($normalized, 0, -strlen('composer.json'));
                    break;
                }

                if ($normalized === 'composer.json') {
                    $composerEntry = $normalized;
                    $root = '';
                    break;
                }
            }

            if (! $composerEntry) {
                throw new RuntimeException('composer.json not found inside ZIP. This may not be an official Strata release package.');
            }

            $composerJson = $zip->getFromName($composerEntry);
            if ($composerJson === false) {
                throw new RuntimeException('Could not read composer.json from the ZIP package.');
            }

            $decoded = json_decode($composerJson, true);
            $version = $decoded['version'] ?? null;
            $name = strtolower((string) ($decoded['name'] ?? ''));

            if (! $version || ! str_contains($name, 'strata')) {
                throw new RuntimeException('Could not detect a valid Strata release version from the ZIP package.');
            }

            return [
                'version' => (string) $version,
                'name' => $name,
                'root' => $root,
            ];
        } finally {
            $zip->close();
        }
    }

    private function extractZip(string $zipPath, string $rootPrefix = ''): int
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ZIP file could not be opened. It may be corrupted.');
        }

        $dest = rtrim(base_path(), DIRECTORY_SEPARATOR);
        $count = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (! is_string($entryName)) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $entryName);
                if ($rootPrefix !== '' && str_starts_with($normalized, $rootPrefix)) {
                    $normalized = substr($normalized, strlen($rootPrefix));
                }

                $normalized = ltrim($normalized, '/');
                if ($normalized === '' || $this->shouldSkip($normalized)) {
                    continue;
                }

                $target = $dest.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);

                if (str_ends_with($entryName, '/')) {
                    if (! is_dir($target)) {
                        mkdir($target, 0755, true);
                    }

                    continue;
                }

                $dir = dirname($target);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $contents = $zip->getFromIndex($i);
                if ($contents === false) {
                    throw new RuntimeException("Failed to extract {$normalized} from the ZIP package.");
                }

                $bytes = file_put_contents($target, $contents);
                if ($bytes === false) {
                    throw new RuntimeException("Failed to write {$normalized} during extraction.");
                }

                $count++;
            }
        } finally {
            $zip->close();
        }

        return $count;
    }

    private function shouldSkip(string $name): bool
    {
        return str_starts_with($name, '.env')
            || $name === 'storage/installed.lock'
            || str_starts_with($name, 'storage/logs/')
            || str_starts_with($name, 'storage/framework/sessions/')
            || str_starts_with($name, 'storage/framework/cache/data/')
            || str_starts_with($name, 'storage/app/public/');
    }

    private function appVersion(): string
    {
        $composerJson = base_path('composer.json');
        if (is_file($composerJson)) {
            $decoded = json_decode(file_get_contents($composerJson), true);
            if (! empty($decoded['version'])) {
                return (string) $decoded['version'];
            }
        }

        return '1.0-RC1';
    }

    private function readLock(): array
    {
        $lockPath = storage_path('installed.lock');
        if (! is_file($lockPath)) {
            return [];
        }

        return json_decode(file_get_contents($lockPath), true) ?? [];
    }
}
