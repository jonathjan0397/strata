<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use PDO;
use PDOException;
use Throwable;

class InstallerController extends Controller
{
    public function index(): Response
    {
        // Auto-detect install type: vendor/ present = ZIP/shared install
        $installType = is_dir(base_path('vendor')) ? 'zip' : 'dev';

        return Inertia::render('Install/Welcome', [
            'installType' => $installType,
        ]);
    }

    /** Step 1 — Check server requirements. */
    public function requirements(): JsonResponse
    {
        $uploadMax  = $this->parseBytes(ini_get('upload_max_filesize'));
        $postMax    = $this->parseBytes(ini_get('post_max_size'));
        $maxExec    = (int) ini_get('max_execution_time');
        $memLimit   = $this->parseBytes(ini_get('memory_limit'));

        $checks = [
            'php_version' => [
                'label'  => 'PHP >= 8.3',
                'pass'   => version_compare(PHP_VERSION, '8.3.0', '>='),
                'detail' => PHP_VERSION,
            ],
            'pdo' => [
                'label' => 'PDO extension',
                'pass'  => extension_loaded('pdo'),
            ],
            'pdo_mysql' => [
                'label' => 'PDO MySQL driver',
                'pass'  => extension_loaded('pdo_mysql'),
            ],
            'mbstring' => [
                'label' => 'mbstring extension',
                'pass'  => extension_loaded('mbstring'),
            ],
            'openssl' => [
                'label' => 'OpenSSL extension',
                'pass'  => extension_loaded('openssl'),
            ],
            'tokenizer' => [
                'label' => 'Tokenizer extension',
                'pass'  => extension_loaded('tokenizer'),
            ],
            'json' => [
                'label' => 'JSON extension',
                'pass'  => extension_loaded('json'),
            ],
            'ctype' => [
                'label' => 'Ctype extension',
                'pass'  => extension_loaded('ctype'),
            ],
            'bcmath' => [
                'label' => 'BCMath extension',
                'pass'  => extension_loaded('bcmath'),
            ],
            'file_uploads' => [
                'label'  => 'File uploads enabled',
                'pass'   => (bool) ini_get('file_uploads'),
                'detail' => ini_get('file_uploads') ? 'enabled' : 'disabled',
            ],
            'upload_max_filesize' => [
                'label'  => 'upload_max_filesize >= 10 MB',
                'pass'   => $uploadMax >= 10 * 1024 * 1024,
                'detail' => ini_get('upload_max_filesize'),
            ],
            'post_max_size' => [
                'label'  => 'post_max_size >= 10 MB',
                'pass'   => $postMax >= 10 * 1024 * 1024,
                'detail' => ini_get('post_max_size'),
            ],
            'max_execution_time' => [
                'label'  => 'max_execution_time >= 60s (or 0)',
                'pass'   => $maxExec === 0 || $maxExec >= 60,
                'detail' => $maxExec === 0 ? 'unlimited' : $maxExec . 's',
            ],
            'memory_limit' => [
                'label'  => 'memory_limit >= 128 MB',
                'pass'   => $memLimit === -1 || $memLimit >= 128 * 1024 * 1024,
                'detail' => ini_get('memory_limit'),
            ],
            'mod_rewrite' => [
                'label'  => 'mod_rewrite / URL rewriting',
                'pass'   => $this->checkModRewrite(),
                'detail' => $this->checkModRewrite() ? 'available' : 'not detected',
                'warn'   => true, // warning, not hard block
            ],
            'symlink' => [
                'label'  => 'symlink() function',
                'pass'   => function_exists('symlink'),
                'detail' => function_exists('symlink') ? 'available' : 'disabled (controller fallback will be used)',
                'warn'   => true, // warning — installer will use fallback
            ],
            'storage_writable' => [
                'label'  => 'storage/ writable',
                'pass'   => is_writable(storage_path()),
                'detail' => storage_path(),
            ],
            'bootstrap_writable' => [
                'label'  => 'bootstrap/cache/ writable',
                'pass'   => is_writable(base_path('bootstrap/cache')),
                'detail' => base_path('bootstrap/cache'),
            ],
            'env_writable' => [
                'label'  => '.env writable (or creatable)',
                'pass'   => is_writable(base_path()) || is_writable(base_path('.env')),
                'detail' => base_path('.env'),
            ],
        ];

        // Warnings don't block install — only hard failures do.
        $allPass = collect($checks)->every(fn ($c) => $c['pass'] || ($c['warn'] ?? false));

        return response()->json([
            'checks'   => $checks,
            'all_pass' => $allPass,
        ]);
    }

    /** Step 2 — Test database connection. */
    public function testDatabase(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'db_host'     => ['required', 'string'],
            'db_port'     => ['required', 'integer', 'min:1', 'max:65535'],
            'db_name'     => ['required', 'string'],
            'db_username' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'error' => $v->errors()->first()], 422);
        }

        // Passwords are base64-encoded client-side to avoid WAF false positives.
        $rawB64  = $request->db_password ?? '';
        $decoded = $rawB64 ? base64_decode($rawB64) : '';
        $request->merge(['db_password' => $decoded]);

        try {
            $dsn = "mysql:host={$request->db_host};port={$request->db_port};dbname={$request->db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $request->db_username, $decoded, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT            => 5,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            return response()->json(['success' => true, 'version' => $version]);
        } catch (PDOException $e) {
            \Illuminate\Support\Facades\Log::debug('INSTALL_DB_TEST', [
                'host'    => $request->db_host,
                'port'    => $request->db_port,
                'db'      => $request->db_name,
                'user'    => $request->db_username,
                'b64_len' => strlen($rawB64),
                'dec_len' => strlen($decoded),
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Step 3 — Run the full installation. */
    public function install(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'app_name'         => ['required', 'string', 'max:100'],
            'app_url'          => ['required', 'url'],
            'db_host'          => ['required', 'string'],
            'db_port'          => ['required', 'integer'],
            'db_name'          => ['required', 'string'],
            'db_username'      => ['required', 'string'],
            'db_password'      => ['nullable', 'string'],
            'admin_name'       => ['required', 'string', 'max:100'],
            'admin_email'      => ['required', 'email'],
            'admin_password'   => ['required', 'string', 'min:8'],
            'queue_connection' => ['required', 'string', 'in:sync,database'],
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        // Passwords are base64-encoded client-side to avoid WAF false positives.
        $request->merge([
            'admin_password' => base64_decode($request->admin_password),
            'db_password'    => $request->db_password ? base64_decode($request->db_password) : '',
        ]);

        try {
            // 1. Write .env
            $this->writeEnv($request);

            // 2. Re-bootstrap config so migrations use the new DB credentials
            $this->rebootConfig($request);

            // 3. Run migrations
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);

            // 4. Run seeders
            foreach (['RolesAndPermissionsSeeder', 'EmailTemplatesSeeder', 'DepartmentsSeeder', 'SettingsSeeder'] as $seeder) {
                Artisan::call('db:seed', [
                    '--class'          => $seeder,
                    '--force'          => true,
                    '--no-interaction' => true,
                ]);
            }

            // 5. Create admin user
            $this->createAdminUser($request);

            // 6. Create storage symlink; fall back to controller-served files if symlinks are blocked.
            $storageMode = 'symlink';
            try {
                Artisan::call('storage:link', ['--force' => true]);
            } catch (Throwable) {
                $storageMode = 'controller';
            }

            // If symlink was not actually created (no exception but still missing), use fallback.
            if ($storageMode === 'symlink' && ! is_link(public_path('storage'))) {
                $storageMode = 'controller';
            }

            // 7. Cache config for production (skip on sync/shared — may not have write access to cache dir)
            try {
                Artisan::call('config:cache');
                Artisan::call('route:cache');
            } catch (Throwable) {
                // Non-fatal on shared hosting
            }

            // 8. Write lock file — blocks future access to /install
            $version = $this->appVersion();
            file_put_contents(
                storage_path('installed.lock'),
                json_encode([
                    'installed_at'   => now()->toIso8601String(),
                    'version'        => $version,
                    'queue'          => $request->queue_connection,
                    'storage_mode'   => $storageMode,
                ])
            );

            return response()->json([
                'success'      => true,
                'queue'        => $request->queue_connection,
                'storage_mode' => $storageMode,
                'app_url'      => rtrim($request->app_url, '/'),
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function writeEnv(Request $request): void
    {
        $appKey   = 'base64:'.base64_encode(random_bytes(32));
        $appUrl   = rtrim($request->app_url, '/');
        $appName  = addslashes($request->app_name);
        $queueConn = $request->queue_connection ?? 'sync';

        $env = <<<ENV
APP_NAME="{$appName}"
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL={$appUrl}
APP_INSTALLED=true

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST={$request->db_host}
DB_PORT={$request->db_port}
DB_DATABASE={$request->db_name}
DB_USERNAME={$request->db_username}
DB_PASSWORD={$request->db_password}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION={$queueConn}
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=sendmail
MAIL_FROM_ADDRESS="noreply@{$this->hostFromUrl($appUrl)}"
MAIL_FROM_NAME="{$appName}"

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
STRIPE_CURRENCY=usd

PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_MODE=sandbox
PAYPAL_CURRENCY=USD

REGISTRAR_DRIVER=namecheap
NAMECHEAP_SANDBOX=true
NAMECHEAP_API_USER=
NAMECHEAP_API_KEY=
NAMECHEAP_CLIENT_IP=

ENOM_SANDBOX=true
ENOM_UID=
ENOM_PW=

OPENSRS_SANDBOX=true
OPENSRS_API_KEY=
OPENSRS_RESELLER_USERNAME=
ENV;

        file_put_contents(base_path('.env'), $env);
        chmod(base_path('.env'), 0600);
    }

    private function rebootConfig(Request $request): void
    {
        config([
            'database.default'                    => 'mysql',
            'database.connections.mysql.host'     => $request->db_host,
            'database.connections.mysql.port'     => $request->db_port,
            'database.connections.mysql.database' => $request->db_name,
            'database.connections.mysql.username' => $request->db_username,
            'database.connections.mysql.password' => $request->db_password ?? '',
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function createAdminUser(Request $request): void
    {
        $user = \App\Models\User::updateOrCreate(
            ['email' => $request->admin_email],
            [
                'name'              => $request->admin_name,
                'password'          => Hash::make($request->admin_password),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }
    }

    private function hostFromUrl(string $url): string
    {
        return parse_url($url, PHP_URL_HOST) ?? 'localhost';
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
        return '1.1.0';
    }

    private function checkModRewrite(): bool
    {
        if (function_exists('apache_get_modules')) {
            return in_array('mod_rewrite', apache_get_modules(), true);
        }
        // On hosts where apache_get_modules() is unavailable, assume true
        // (the installer itself is reachable, which implies rewriting works).
        return true;
    }

    /** Convert PHP ini size strings (128M, 2G, 512K) to bytes. */
    private function parseBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return -1;
        }
        $last = strtolower(substr($value, -1));
        $num  = (int) $value;
        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }
}
