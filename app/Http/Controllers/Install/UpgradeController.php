<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;
use ZipArchive;

class UpgradeController extends Controller
{
    // ── Pre-flight page ────────────────────────────────────────────────────────

    public function index(): Response
    {
        abort_unless(file_exists(storage_path('installed.lock')), 404, 'Strata is not installed.');

        $lock = json_decode(file_get_contents(storage_path('installed.lock')), true) ?? [];
        $currentVersion = $lock['version'] ?? 'unknown';
        $codeVersion = $this->appVersion();

        // Pre-flight checks
        $checks = [
            [
                'label' => 'PHP >= 8.3',
                'pass' => version_compare(PHP_VERSION, '8.3.0', '>='),
                'detail' => PHP_VERSION,
            ],
            [
                'label' => 'PHP ext-zip (file extraction)',
                'pass' => extension_loaded('zip'),
                'detail' => extension_loaded('zip') ? 'available' : 'missing — upload files via FTP and use "files already uploaded" mode',
                'warn' => true, // Not a hard failure — wizard can skip extraction
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
            fn ($c) => ! $c['pass'] && empty($c['warn'])
        );

        return Inertia::render('Install/Upgrade', [
            'currentVersion' => $currentVersion,
            'codeVersion' => $codeVersion,
            'alreadyUpdated' => $currentVersion !== $codeVersion,
            'hasZipExtension' => extension_loaded('zip'),
            'checks' => $checks,
            'hardFail' => $hardFail,
            'installedAt' => $lock['installed_at'] ?? null,
            'lastUpgradedAt' => $lock['upgraded_at'] ?? null,
        ]);
    }

    // ── Verify admin credentials (AJAX) ────────────────────────────────────────

    public function verify(Request $request): JsonResponse
    {
        $email = trim((string) $request->input('email', ''));
        $password = base64_decode((string) $request->input('password', ''));

        if (! $email || ! $password) {
            return response()->json(['valid' => false, 'error' => 'Email and password are required.'], 422);
        }

        try {
            $user = User::where('email', $email)
                ->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
                ->first();

            if (! $user || ! Hash::check($password, $user->password)) {
                return response()->json(['valid' => false, 'error' => 'Invalid credentials or account is not a super-admin.'], 401);
            }

            return response()->json(['valid' => true]);
        } catch (Throwable $e) {
            return response()->json(['valid' => false, 'error' => 'Database error: '.$e->getMessage()], 500);
        }
    }

    // ── Read new version from an uploaded ZIP without full extraction ───────────

    public function peekZip(Request $request): JsonResponse
    {
        if (! $request->hasFile('zip') || ! $request->file('zip')->isValid()) {
            return response()->json(['success' => false, 'error' => 'No ZIP uploaded.'], 422);
        }

        if (! extension_loaded('zip')) {
            return response()->json(['success' => false, 'error' => 'PHP ext-zip not available.'], 500);
        }

        $zip = new ZipArchive;
        if ($zip->open($request->file('zip')->getRealPath()) !== true) {
            return response()->json(['success' => false, 'error' => 'Invalid or corrupted ZIP file.'], 422);
        }

        $composerJson = $zip->getFromName('composer.json');
        $zip->close();

        if ($composerJson === false) {
            return response()->json(['success' => false, 'error' => 'composer.json not found inside ZIP — this may not be a Strata release package.'], 422);
        }

        $decoded = json_decode($composerJson, true);
        $version = $decoded['version'] ?? null;
        $name = $decoded['name'] ?? null;

        if (! $version || ! str_contains((string) $name, 'strata')) {
            return response()->json(['success' => false, 'error' => 'Could not detect Strata version from ZIP. Ensure you are uploading an official Strata release package.'], 422);
        }

        return response()->json([
            'success' => true,
            'version' => $version,
        ]);
    }

    // ── Run the upgrade ─────────────────────────────────────────────────────────

    public function run(Request $request): JsonResponse
    {
        @set_time_limit(0);

        // Re-verify credentials every time — no session trust
        $email = trim((string) $request->input('email', ''));
        $password = base64_decode((string) $request->input('password', ''));

        try {
            $user = User::where('email', $email)
                ->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
                ->first();

            if (! $user || ! Hash::check($password, $user->password)) {
                return response()->json(['success' => false, 'error' => 'Credential check failed.'], 401);
            }

            // Backfill email_verified_at — was not in $fillable so installs before RC4 left it null
            if (! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Auth error: '.$e->getMessage()], 500);
        }

        $log = [];

        try {
            // ── 1. Extract ZIP (optional — skipped if no file uploaded) ──────────
            if ($request->hasFile('zip') && $request->file('zip')->isValid()) {
                if (! extension_loaded('zip')) {
                    throw new \RuntimeException('PHP ext-zip is not available. Upload files via FTP instead and re-run without a ZIP.');
                }

                $zip = new ZipArchive;
                if ($zip->open($request->file('zip')->getRealPath()) !== true) {
                    throw new \RuntimeException('ZIP file could not be opened. It may be corrupted.');
                }

                $dest = rtrim(base_path(), '/');
                $count = 0;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);

                    if ($this->shouldSkip($name)) {
                        continue;
                    }

                    $target = $dest.'/'.$name;

                    if (str_ends_with($name, '/')) {
                        if (! is_dir($target)) {
                            mkdir($target, 0755, true);
                        }
                    } else {
                        $dir = dirname($target);
                        if (! is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        $bytes = file_put_contents($target, $zip->getFromIndex($i));
                        if ($bytes !== false) {
                            $count++;
                        }
                    }
                }

                $zip->close();
                $log[] = "Extracted {$count} files from ZIP.";
            } else {
                $log[] = 'File extraction skipped — using already-uploaded code.';
            }

            // ── 2. Run database migrations ────────────────────────────────────
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
            $migrateOutput = trim(Artisan::output());
            $log[] = 'Migrations: '.($migrateOutput ?: 'Nothing to migrate.');

            // ── 3. Clear all caches ───────────────────────────────────────────
            foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear', 'event:clear'] as $cmd) {
                try {
                    Artisan::call($cmd);
                } catch (Throwable) {
                    // Non-fatal
                }
            }

            // Re-cache for production
            try {
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                $log[] = 'Config and route caches rebuilt.';
            } catch (Throwable) {
                $log[] = 'Cache rebuild skipped (non-fatal).';
            }

            // ── 4. Update installed.lock with new version ─────────────────────
            $lockPath = storage_path('installed.lock');
            $lock = json_decode(file_get_contents($lockPath), true) ?? [];
            $newVersion = $this->appVersion();

            $lock['version'] = $newVersion;
            $lock['upgraded_at'] = now()->toIso8601String();

            // Backfill install_token and install_secret for installs that pre-date their introduction
            if (empty($lock['install_token'])) {
                $lock['install_token'] = Str::uuid()->toString();
            }
            if (empty($lock['install_secret'])) {
                $lock['install_secret'] = bin2hex(random_bytes(32));
            }

            file_put_contents($lockPath, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $log[] = "Lock file updated → {$newVersion}.";

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
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Files and directories inside the ZIP that must never be overwritten.
     * The release ZIP already excludes most of these, but we guard here too.
     */
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
                return $decoded['version'];
            }
        }

        return '1.0-RC1';
    }
}
