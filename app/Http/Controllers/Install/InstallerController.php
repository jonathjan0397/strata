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
        return Inertia::render('Install/Welcome');
    }

    /** Step 1 — Check server requirements. */
    public function requirements(): JsonResponse
    {
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

        $allPass = collect($checks)->every(fn ($c) => $c['pass']);

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

        try {
            $dsn = "mysql:host={$request->db_host};port={$request->db_port};dbname={$request->db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $request->db_username, $request->db_password ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT            => 5,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            return response()->json(['success' => true, 'version' => $version]);
        } catch (PDOException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Step 3 — Run the full installation. */
    public function install(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'app_name'     => ['required', 'string', 'max:100'],
            'app_url'      => ['required', 'url'],
            'db_host'      => ['required', 'string'],
            'db_port'      => ['required', 'integer'],
            'db_name'      => ['required', 'string'],
            'db_username'  => ['required', 'string'],
            'db_password'  => ['nullable', 'string'],
            'admin_name'   => ['required', 'string', 'max:100'],
            'admin_email'  => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        try {
            // 1. Write .env
            $this->writeEnv($request);

            // 2. Re-bootstrap config so migrations use the new DB credentials
            $this->rebootConfig($request);

            // 3. Run migrations
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);

            // 4. Run seeders
            Artisan::call('db:seed', [
                '--class'         => 'RolesAndPermissionsSeeder',
                '--force'         => true,
                '--no-interaction'=> true,
            ]);

            // 5. Create admin user
            $this->createAdminUser($request);

            // 6. Cache config for production
            Artisan::call('config:cache');
            Artisan::call('route:cache');

            // 7. Write lock file — blocks future access to /install
            file_put_contents(
                storage_path('installed.lock'),
                json_encode(['installed_at' => now()->toIso8601String(), 'version' => '0.2.0'])
            );

            return response()->json(['success' => true]);

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
        $appKey  = 'base64:'.base64_encode(random_bytes(32));
        $appUrl  = rtrim($request->app_url, '/');
        $appName = addslashes($request->app_name);

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
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@{$this->hostFromUrl($appUrl)}"
MAIL_FROM_NAME="{$appName}"
ENV;

        file_put_contents(base_path('.env'), $env);
        chmod(base_path('.env'), 0600);
    }

    private function rebootConfig(Request $request): void
    {
        // Update the running config so Artisan migrations hit the right DB
        config([
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
        $user = \App\Models\User::firstOrCreate(
            ['email' => $request->admin_email],
            [
                'name'              => $request->admin_name,
                'password'          => Hash::make($request->admin_password),
                'email_verified_at' => now(),
            ]
        );

        // Ensure roles table is ready before assigning
        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }
    }

    private function hostFromUrl(string $url): string
    {
        return parse_url($url, PHP_URL_HOST) ?? 'localhost';
    }
}
