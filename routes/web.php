<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Client;
use App\Http\Controllers\Install\InstallerController;
use App\Http\Controllers\Install\UpgradeController;
use App\Http\Controllers\Portal\DomainSearchController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\WidgetController;
use App\Http\Controllers\Profile\SessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TicketAttachmentController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;

// ── Storage fallback (shared hosting: symlink disabled) ──────────────────────
// Only registered when public/storage symlink does not exist.
if (! is_link(public_path('storage'))) {
    Route::get('storage/{path}', [StorageController::class, 'serve'])
        ->where('path', '.*')
        ->name('storage.serve');
}

// ── Stripe webhook (CSRF exempt — verified by signature) ─────────────────────
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

// ── Email pipe endpoint (CSRF exempt — authenticated by pipe_token) ───────────
Route::post('pipe/{token}', [Admin\MailboxPipeController::class, 'receive'])->name('pipe.receive');

// ── Installer (blocked by CheckInstalled once storage/installed.lock exists) ─
// Session/cookie/CSRF middleware are stripped so no laravel-session cookie is
// ever issued during install — prevents ModSecurity false-positive 403s on
// shared hosting where the encrypted cookie value triggers SQL-injection rules.
Route::prefix('install')->name('install.')->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    VerifyCsrfToken::class,
])->group(function () {
    Route::get('/', [InstallerController::class, 'index'])->name('index');
    Route::get('/requirements', [InstallerController::class, 'requirements'])->name('requirements');
    Route::post('/test-database', [InstallerController::class, 'testDatabase'])->name('test-database');
    Route::post('/run', [InstallerController::class, 'install'])->name('run');
});

// ── Upgrade wizard (requires installed.lock; session/CSRF stripped like install) ─
// Credential verification is done directly against the users table instead of
// Laravel's session-based auth, so the same ModSecurity-safe rules apply.
Route::prefix('upgrade')->name('upgrade.')->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    VerifyCsrfToken::class,
])->group(function () {
    Route::get('/', [UpgradeController::class, 'index'])->name('index');
    Route::post('/verify', [UpgradeController::class, 'verify'])->name('verify');
    Route::post('/peek', [UpgradeController::class, 'peekZip'])->name('peek');
    Route::post('/run', [UpgradeController::class, 'run'])->name('run');
});

// ── Public portal (no auth required) ─────────────────────────────────────────
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/', [PortalController::class, 'home'])->name('home');
    Route::get('/services', [PortalController::class, 'products'])->name('portal.products');
    Route::get('/kb', [PortalController::class, 'kb'])->name('portal.kb');
    Route::get('/kb/{slug}', [PortalController::class, 'kbArticle'])->name('portal.kb.show');
    Route::get('/announcements', [PortalController::class, 'announcements'])->name('portal.announcements');
    Route::get('/domain-search', [DomainSearchController::class, 'search'])->name('domain.search');
});

// ── Embeddable widget API (JSON, CORS-open, read-only) ────────────────────────
Route::middleware('throttle:60,1')->prefix('api/widget')->name('widget.')->group(function () {
    Route::get('products', [WidgetController::class, 'products'])->name('products');
    Route::get('announcements', [WidgetController::class, 'announcements'])->name('announcements');
    Route::get('kb', [WidgetController::class, 'kb'])->name('kb');
    Route::get('domain-search', [WidgetController::class, 'domainSearch'])->name('domain-search');
});

Route::get('strata-widget.js', [WidgetController::class, 'widgetJs'])->name('widget.js');

// ── Guest ────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// ── OAuth2 ───────────────────────────────────────────────────────────────────
Route::get('auth/{provider}/redirect', [SocialiteController::class, 'redirect'])->name('socialite.redirect');
Route::get('auth/{provider}/callback', [SocialiteController::class, 'callback'])->name('socialite.callback');

// ── 2FA challenge ────────────────────────────────────────────────────────────
Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);

// ── Auth (no verified requirement) ───────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('email/verify', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)->middleware('signed')->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->middleware('throttle:6,1')->name('verification.send');
});

// ── Auth + Verified ───────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // Root handled by public portal controller (redirects if logged in)

    // ── 2FA management ──────────────────────────────────────────────────────
    Route::post('user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])->name('two-factor.enable');
    Route::get('user/two-factor-qr-code', [TwoFactorAuthenticationController::class, 'qrCode'])->name('two-factor.qr-code');
    Route::post('user/confirmed-two-factor-authentication', [TwoFactorAuthenticationController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])->name('two-factor.disable');

    // ── Profile ──────────────────────────────────────────────────────────────
    Route::get('profile', [ProfileController::class,  'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class,  'update'])->name('profile.update');
    Route::get('profile/security', fn () => Inertia::render('Profile/Security'))->name('profile.security');
    Route::get('profile/sessions', [SessionController::class, 'index'])->name('profile.sessions');
    Route::delete('profile/sessions/{session}', [SessionController::class, 'destroy'])->name('profile.sessions.destroy');
    Route::delete('profile/sessions', [SessionController::class, 'destroyOthers'])->name('profile.sessions.destroy-others');

    // ── Admin panel ──────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->middleware(['admin', 'require.2fa'])->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        // Clients
        Route::get('clients', [Admin\ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [Admin\ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [Admin\ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}', [Admin\ClientController::class, 'show'])->name('clients.show');
        Route::patch('clients/{client}', [Admin\ClientController::class, 'update'])->name('clients.update');
        Route::post('clients/{client}/suspend', [Admin\ClientController::class, 'suspend'])->name('clients.suspend');
        Route::post('clients/{client}/verify-email', [Admin\ClientController::class, 'verifyEmail'])->name('clients.verify-email');
        Route::post('clients/{client}/credit', [Admin\ClientController::class, 'addCredit'])->name('clients.credit');
        Route::post('clients/{client}/notes', [Admin\ClientController::class, 'storeNote'])->name('clients.notes.store');
        Route::delete('clients/{client}/notes/{note}', [Admin\ClientController::class, 'destroyNote'])->name('clients.notes.destroy');
        Route::post('clients/{client}/email', [Admin\ClientController::class, 'sendEmail'])->name('clients.email');
        Route::post('clients/{client}/tasks', [Admin\ClientController::class, 'storeTask'])->name('clients.tasks.store');
        Route::patch('clients/{client}/tasks/{task}/complete', [Admin\ClientController::class, 'completeTask'])->name('clients.tasks.complete');
        Route::delete('clients/{client}/tasks/{task}', [Admin\ClientController::class, 'destroyTask'])->name('clients.tasks.destroy');

        Route::get('client-groups', [Admin\ClientGroupController::class, 'index'])->name('client-groups.index');
        Route::post('client-groups', [Admin\ClientGroupController::class, 'store'])->name('client-groups.store');
        Route::patch('client-groups/{clientGroup}', [Admin\ClientGroupController::class, 'update'])->name('client-groups.update');
        Route::delete('client-groups/{clientGroup}', [Admin\ClientGroupController::class, 'destroy'])->name('client-groups.destroy');
        Route::post('clients/{client}/assign-group', [Admin\ClientGroupController::class, 'assignClient'])->name('client-groups.assign');

        // Promo Codes
        Route::get('promo-codes', [Admin\PromoCodeController::class, 'index'])->name('promo-codes.index');
        Route::get('promo-codes/create', [Admin\PromoCodeController::class, 'create'])->name('promo-codes.create');
        Route::post('promo-codes', [Admin\PromoCodeController::class, 'store'])->name('promo-codes.store');
        Route::get('promo-codes/{promoCode}/edit', [Admin\PromoCodeController::class, 'edit'])->name('promo-codes.edit');
        Route::patch('promo-codes/{promoCode}', [Admin\PromoCodeController::class, 'update'])->name('promo-codes.update');
        Route::delete('promo-codes/{promoCode}', [Admin\PromoCodeController::class, 'destroy'])->name('promo-codes.destroy');

        // Products
        Route::get('products', [Admin\ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [Admin\ProductController::class, 'create'])->name('products.create');
        Route::post('products', [Admin\ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [Admin\ProductController::class, 'edit'])->name('products.edit');
        Route::patch('products/{product}', [Admin\ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [Admin\ProductController::class, 'destroy'])->name('products.destroy');

        // Services
        Route::get('services', [Admin\ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}', [Admin\ServiceController::class, 'show'])->name('services.show');
        Route::post('services/{service}/approve', [Admin\ServiceController::class, 'approve'])->name('services.approve');
        Route::post('services/{service}/suspend', [Admin\ServiceController::class, 'suspend'])->name('services.suspend');
        Route::post('services/{service}/unsuspend', [Admin\ServiceController::class, 'unsuspend'])->name('services.unsuspend');
        Route::post('services/{service}/terminate', [Admin\ServiceController::class, 'terminate'])->name('services.terminate');
        Route::post('services/{service}/approve-cancellation', [Admin\ServiceController::class, 'approveCancellation'])->name('services.approve-cancellation');
        Route::post('services/{service}/reject-cancellation', [Admin\ServiceController::class, 'rejectCancellation'])->name('services.reject-cancellation');
        Route::post('services/{service}/addons', [Admin\ServiceController::class, 'addAddon'])->name('services.addons.store');
        Route::delete('services/{service}/addons/{serviceAddon}', [Admin\ServiceController::class, 'removeAddon'])->name('services.addons.destroy');

        // Invoices
        Route::get('invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [Admin\InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [Admin\InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/download', [Admin\InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('invoices/{invoice}/mark-paid', [Admin\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
        Route::post('invoices/{invoice}/cancel', [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/send', [Admin\InvoiceController::class, 'sendEmail'])->name('invoices.send');
        Route::post('invoices/{invoice}/credit-notes', [Admin\InvoiceController::class, 'issueCreditNote'])->name('invoices.credit-notes.store');
        Route::post('invoices/{invoice}/credit-notes/{creditNote}/void', [Admin\InvoiceController::class, 'voidCreditNote'])->name('invoices.credit-notes.void');

        // Orders
        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');

        // Support
        Route::get('support', [Admin\SupportController::class, 'index'])->name('support.index');
        Route::post('support/bulk', [Admin\SupportController::class, 'bulkAction'])->name('support.bulk');
        Route::get('support/create', [Admin\SupportController::class, 'create'])->name('support.create');
        Route::post('support', [Admin\SupportController::class, 'store'])->name('support.store');
        Route::get('support/attachments/{attachment}/download', [TicketAttachmentController::class, 'download'])->name('support.attachments.download');
        Route::get('support/{ticket}', [Admin\SupportController::class, 'show'])->name('support.show');
        Route::post('support/{ticket}/reply', [Admin\SupportController::class, 'reply'])->name('support.reply');
        Route::post('support/{ticket}/assign', [Admin\SupportController::class, 'assign'])->name('support.assign');
        Route::post('support/{ticket}/close', [Admin\SupportController::class, 'close'])->name('support.close');
        Route::post('support/{ticket}/reopen', [Admin\SupportController::class, 'reopen'])->name('support.reopen');
        Route::patch('support/{ticket}/priority', [Admin\SupportController::class, 'setPriority'])->name('support.priority');
        Route::patch('support/{ticket}/department', [Admin\SupportController::class, 'transferDepartment'])->name('support.transfer');
        Route::post('support/{ticket}/merge', [Admin\SupportController::class, 'merge'])->name('support.merge');

        // Widget Snippets
        Route::get('widgets', [Admin\WidgetSnippetsController::class, 'index'])->name('widgets.index');

        // Settings
        Route::get('settings', [Admin\SettingController::class, 'index'])->name('settings.index');
        Route::patch('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::patch('settings/mail', [Admin\SettingController::class, 'updateMail'])->name('settings.mail');
        Route::post('settings/mail/test', [Admin\SettingController::class, 'testMail'])->name('settings.mail.test');
        Route::post('settings/mail/deliverability', [Admin\SettingController::class, 'emailDeliverability'])->name('settings.mail.deliverability');
        Route::post('settings/logo', [Admin\SettingController::class, 'uploadLogo'])->name('settings.logo');
        Route::patch('settings/integrations', [Admin\SettingController::class, 'updateIntegrations'])->name('settings.integrations');
        Route::post('settings/license-sync', [Admin\SettingController::class, 'syncLicense'])->name('settings.license-sync');
        Route::post('settings/license-trial', [Admin\SettingController::class, 'startTrial'])->name('settings.license-trial');

        // Maintenance page + actions (super-admin only)
        Route::get('maintenance', fn () => Inertia::render('Admin/Maintenance'))
            ->name('maintenance.index');

        Route::post('maintenance/migrate', function () {
            abort_unless(auth()->user()?->hasRole('super-admin'), 403);
            try {
                Artisan::call('migrate', ['--force' => true]);
                $output = trim(Artisan::output()) ?: 'Nothing to migrate.';

                return response()->json(['success' => true, 'output' => $output]);
            } catch (Throwable $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage(), 'output' => trim(Artisan::output())], 500);
            }
        })->name('maintenance.migrate');

        Route::post('maintenance/repair-schema', function () {
            abort_unless(auth()->user()?->hasRole('super-admin'), 403);
            $lines = [];
            try {
                // 1. modules.type enum — add hestia + cwp if missing
                DB::statement("ALTER TABLE modules MODIFY COLUMN type ENUM('cpanel','plesk','directadmin','hestia','cwp','vestacp','cyberpanel','generic') NOT NULL DEFAULT 'generic'");
                $lines[] = '✓ modules.type enum updated (hestia, cwp added)';
            } catch (\Throwable $e) { $lines[] = '✗ modules.type: '.$e->getMessage(); }

            try {
                // 2. modules.local_hostname
                DB::statement("ALTER TABLE modules ADD COLUMN IF NOT EXISTS local_hostname VARCHAR(255) NULL AFTER hostname");
                $lines[] = '✓ modules.local_hostname column ensured';
            } catch (\Throwable $e) { $lines[] = '✗ modules.local_hostname: '.$e->getMessage(); }

            try {
                // 3. modules.local_port
                DB::statement("ALTER TABLE modules ADD COLUMN IF NOT EXISTS local_port SMALLINT UNSIGNED NULL AFTER local_hostname");
                $lines[] = '✓ modules.local_port column ensured';
            } catch (\Throwable $e) { $lines[] = '✗ modules.local_port: '.$e->getMessage(); }

            try {
                // 4. mailbox_pipes imap fields
                DB::statement("ALTER TABLE mailbox_pipes
                    ADD COLUMN IF NOT EXISTS imap_host VARCHAR(255) NULL,
                    ADD COLUMN IF NOT EXISTS imap_port INT NULL DEFAULT 993,
                    ADD COLUMN IF NOT EXISTS imap_username VARCHAR(255) NULL,
                    ADD COLUMN IF NOT EXISTS imap_password_enc TEXT NULL,
                    ADD COLUMN IF NOT EXISTS imap_encryption VARCHAR(16) NULL DEFAULT 'ssl',
                    ADD COLUMN IF NOT EXISTS imap_last_checked_at TIMESTAMP NULL");
                $lines[] = '✓ mailbox_pipes IMAP columns ensured';
            } catch (\Throwable $e) { $lines[] = '✗ mailbox_pipes imap: '.$e->getMessage(); }

            try {
                // 5. tld_pricing table
                DB::statement("CREATE TABLE IF NOT EXISTS `tld_pricing` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `tld` VARCHAR(32) NOT NULL,
                    `register_cost` DECIMAL(10,4) NULL,
                    `renew_cost` DECIMAL(10,4) NULL,
                    `transfer_cost` DECIMAL(10,4) NULL,
                    `markup_type` ENUM('fixed','percent') NOT NULL DEFAULT 'percent',
                    `markup_value` DECIMAL(10,4) NOT NULL DEFAULT 0,
                    `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `last_synced_at` TIMESTAMP NULL,
                    `created_at` TIMESTAMP NULL,
                    `updated_at` TIMESTAMP NULL,
                    UNIQUE KEY `tld_pricing_tld_unique` (`tld`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $lines[] = '✓ tld_pricing table ensured';
            } catch (\Throwable $e) { $lines[] = '✗ tld_pricing: '.$e->getMessage(); }

            $success = ! str_contains(implode("\n", $lines), '✗');
            return response()->json(['success' => $success, 'output' => implode("\n", $lines)]);
        })->name('maintenance.repair-schema');

        Route::post('maintenance/cache', function () {
            abort_unless(auth()->user()?->hasRole('super-admin'), 403);
            try {
                $lines = [];
                foreach (['cache:clear', 'config:clear', 'route:clear', 'view:clear'] as $cmd) {
                    Artisan::call($cmd);
                    $out = trim(Artisan::output());
                    $lines[] = $out ?: $cmd.' done.';
                }

                return response()->json(['success' => true, 'output' => implode("\n", $lines)]);
            } catch (Throwable $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        })->name('maintenance.cache');

        // Departments
        Route::get('settings/departments', [Admin\DepartmentController::class, 'index'])->name('departments.index');
        Route::post('settings/departments', [Admin\DepartmentController::class, 'store'])->name('departments.store');
        Route::patch('settings/departments/{department}', [Admin\DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('settings/departments/{department}', [Admin\DepartmentController::class, 'destroy'])->name('departments.destroy');

        // Canned Responses
        Route::get('settings/canned-responses', [Admin\CannedResponseController::class, 'index'])->name('canned-responses.index');
        Route::post('settings/canned-responses', [Admin\CannedResponseController::class, 'store'])->name('canned-responses.store');
        Route::patch('settings/canned-responses/{cannedResponse}', [Admin\CannedResponseController::class, 'update'])->name('canned-responses.update');
        Route::delete('settings/canned-responses/{cannedResponse}', [Admin\CannedResponseController::class, 'destroy'])->name('canned-responses.destroy');

        // Mail Pipes (email → ticket)
        Route::get('settings/mail-pipes', [Admin\MailboxPipeController::class, 'index'])->name('mail-pipes.index');
        Route::post('settings/mail-pipes', [Admin\MailboxPipeController::class, 'store'])->name('mail-pipes.store');
        Route::patch('settings/mail-pipes/{mailboxPipe}', [Admin\MailboxPipeController::class, 'update'])->name('mail-pipes.update');
        Route::post('settings/mail-pipes/{mailboxPipe}/token', [Admin\MailboxPipeController::class, 'regenerateToken'])->name('mail-pipes.token');
        Route::delete('settings/mail-pipes/{mailboxPipe}', [Admin\MailboxPipeController::class, 'destroy'])->name('mail-pipes.destroy');

        // Knowledge Base (admin)
        Route::get('kb', [Admin\KbController::class, 'index'])->name('kb.index');
        Route::get('kb/categories', [Admin\KbController::class, 'categories'])->name('kb.categories');
        Route::post('kb/categories', [Admin\KbController::class, 'storeCategory'])->name('kb.categories.store');
        Route::patch('kb/categories/{kbCategory}', [Admin\KbController::class, 'updateCategory'])->name('kb.categories.update');
        Route::delete('kb/categories/{kbCategory}', [Admin\KbController::class, 'destroyCategory'])->name('kb.categories.destroy');
        Route::get('kb/create', [Admin\KbController::class, 'create'])->name('kb.create');
        Route::post('kb', [Admin\KbController::class, 'store'])->name('kb.store');
        Route::get('kb/{kbArticle}/edit', [Admin\KbController::class, 'edit'])->name('kb.edit');
        Route::patch('kb/{kbArticle}', [Admin\KbController::class, 'update'])->name('kb.update');
        Route::delete('kb/{kbArticle}', [Admin\KbController::class, 'destroy'])->name('kb.destroy');
        Route::post('kb/images', [Admin\KbController::class, 'uploadImage'])->name('kb.images.upload');

        // Domains
        Route::get('domains', [Admin\DomainController::class, 'index'])->name('domains.index');
        Route::get('domains/{domain}', [Admin\DomainController::class, 'show'])->name('domains.show');
        Route::post('domains/{domain}/nameservers', [Admin\DomainController::class, 'syncNameservers'])->name('domains.nameservers');
        Route::post('domains/{domain}/lock', [Admin\DomainController::class, 'setLock'])->name('domains.lock');
        Route::post('domains/{domain}/privacy', [Admin\DomainController::class, 'setPrivacy'])->name('domains.privacy');
        Route::post('domains/{domain}/refresh', [Admin\DomainController::class, 'refresh'])->name('domains.refresh');

        // TLD Pricing
        Route::get('tld-pricing', [Admin\TldPricingController::class, 'index'])->name('tld-pricing.index');
        Route::post('tld-pricing', [Admin\TldPricingController::class, 'store'])->name('tld-pricing.store');
        Route::patch('tld-pricing/{tldPrice}', [Admin\TldPricingController::class, 'update'])->name('tld-pricing.update');
        Route::delete('tld-pricing/{tldPrice}', [Admin\TldPricingController::class, 'destroy'])->name('tld-pricing.destroy');
        Route::post('tld-pricing/import', [Admin\TldPricingController::class, 'import'])->name('tld-pricing.import');
        Route::post('tld-pricing/bulk', [Admin\TldPricingController::class, 'bulkUpdate'])->name('tld-pricing.bulk-update');

        // Modules / Servers
        Route::get('modules', [Admin\ModuleController::class, 'index'])->name('modules.index');
        Route::get('modules/create', [Admin\ModuleController::class, 'create'])->name('modules.create');
        Route::post('modules', [Admin\ModuleController::class, 'store'])->name('modules.store');
        Route::get('modules/{module}/edit', [Admin\ModuleController::class, 'edit'])->name('modules.edit');
        Route::patch('modules/{module}', [Admin\ModuleController::class, 'update'])->name('modules.update');
        Route::delete('modules/{module}', [Admin\ModuleController::class, 'destroy'])->name('modules.destroy');
        Route::get('modules/{module}/packages', [Admin\ModuleController::class, 'packages'])->name('modules.packages');
        Route::get('modules/{module}/import', [Admin\ServerImportController::class, 'show'])->name('modules.import');
        Route::post('modules/{module}/import/preview', [Admin\ServerImportController::class, 'preview'])->name('modules.import.preview');
        Route::post('modules/{module}/import', [Admin\ServerImportController::class, 'store'])->name('modules.import.store');
        Route::get('modules/{module}/sync-packages', [Admin\PackageSyncController::class, 'show'])->name('modules.packages.sync');
        Route::post('modules/{module}/sync-packages', [Admin\PackageSyncController::class, 'store'])->name('modules.packages.sync.store');
        Route::post('modules/{module}/create-package', [Admin\PackageSyncController::class, 'createOnPanel'])->name('modules.packages.create');

        // Email Templates
        Route::get('email-templates', [Admin\EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('email-templates/{emailTemplate}/edit', [Admin\EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::patch('email-templates/{emailTemplate}', [Admin\EmailTemplateController::class, 'update'])->name('email-templates.update');

        // Announcements
        Route::get('announcements', [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('announcements/create', [Admin\AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('announcements/images', [Admin\AnnouncementController::class, 'uploadImage'])->name('announcements.images.upload');
        Route::post('announcements', [Admin\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('announcements/{announcement}/edit', [Admin\AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::patch('announcements/{announcement}', [Admin\AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [Admin\AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        // Team (admin + staff) management
        Route::get('staff', [Admin\StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create', [Admin\StaffController::class, 'create'])->name('staff.create');
        Route::post('staff', [Admin\StaffController::class, 'store'])->name('staff.store');
        Route::get('staff/{staff}/edit', [Admin\StaffController::class, 'edit'])->name('staff.edit');
        Route::patch('staff/{staff}', [Admin\StaffController::class, 'update'])->name('staff.update');
        Route::delete('staff/{staff}', [Admin\StaffController::class, 'destroy'])->name('staff.destroy');

        Route::get('audit-log', [Admin\AuditLogController::class, 'index'])->name('audit-log.index');

        // Active sessions
        Route::get('active-sessions', [Admin\ActiveSessionsController::class, 'index'])->name('active-sessions.index');
        Route::delete('active-sessions/{sessionId}', [Admin\ActiveSessionsController::class, 'destroy'])->name('active-sessions.destroy');
        Route::delete('active-sessions/user/{userId}', [Admin\ActiveSessionsController::class, 'destroyUser'])->name('active-sessions.destroy-user');

        // Workflows (Premium)
        Route::middleware('require.feature:workflows')->group(function () {
            Route::get('workflows', [Admin\WorkflowController::class, 'index'])->name('workflows.index');
            Route::get('workflows/create', [Admin\WorkflowController::class, 'create'])->name('workflows.create');
            Route::post('workflows', [Admin\WorkflowController::class, 'store'])->name('workflows.store');
            Route::get('workflows/{workflow}/edit', [Admin\WorkflowController::class, 'edit'])->name('workflows.edit');
            Route::patch('workflows/{workflow}', [Admin\WorkflowController::class, 'update'])->name('workflows.update');
            Route::delete('workflows/{workflow}', [Admin\WorkflowController::class, 'destroy'])->name('workflows.destroy');
            Route::post('workflows/{workflow}/toggle', [Admin\WorkflowController::class, 'toggleActive'])->name('workflows.toggle');
        });

        Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');

        Route::get('tax-rates', [Admin\TaxRateController::class, 'index'])->name('tax-rates.index');
        Route::post('tax-rates', [Admin\TaxRateController::class, 'store'])->name('tax-rates.store');
        Route::patch('tax-rates/{taxRate}', [Admin\TaxRateController::class, 'update'])->name('tax-rates.update');
        Route::delete('tax-rates/{taxRate}', [Admin\TaxRateController::class, 'destroy'])->name('tax-rates.destroy');

        Route::get('email-log', [Admin\EmailLogController::class, 'index'])->name('email-log.index');
        Route::get('email-log/{emailLog}', [Admin\EmailLogController::class, 'show'])->name('email-log.show');

        Route::get('quotes', [Admin\QuoteController::class, 'index'])->name('quotes.index');
        Route::get('quotes/create', [Admin\QuoteController::class, 'create'])->name('quotes.create');
        Route::post('quotes', [Admin\QuoteController::class, 'store'])->name('quotes.store');
        Route::get('quotes/{quote}', [Admin\QuoteController::class, 'show'])->name('quotes.show');
        Route::get('quotes/{quote}/edit', [Admin\QuoteController::class, 'edit'])->name('quotes.edit');
        Route::patch('quotes/{quote}', [Admin\QuoteController::class, 'update'])->name('quotes.update');
        Route::delete('quotes/{quote}', [Admin\QuoteController::class, 'destroy'])->name('quotes.destroy');
        Route::post('quotes/{quote}/send', [Admin\QuoteController::class, 'send'])->name('quotes.send');
        Route::post('quotes/{quote}/convert', [Admin\QuoteController::class, 'convert'])->name('quotes.convert');

        // Addons catalog
        Route::get('addons', [Admin\AddonController::class, 'index'])->name('addons.index');
        Route::get('addons/create', [Admin\AddonController::class, 'create'])->name('addons.create');
        Route::post('addons', [Admin\AddonController::class, 'store'])->name('addons.store');
        Route::get('addons/{addon}/edit', [Admin\AddonController::class, 'edit'])->name('addons.edit');
        Route::patch('addons/{addon}', [Admin\AddonController::class, 'update'])->name('addons.update');
        Route::delete('addons/{addon}', [Admin\AddonController::class, 'destroy'])->name('addons.destroy');

        // Affiliates (Premium)
        Route::middleware('require.feature:affiliates')->group(function () {
            Route::get('affiliates', [Admin\AffiliateController::class, 'index'])->name('affiliates.index');
            Route::post('affiliates', [Admin\AffiliateController::class, 'store'])->name('affiliates.store');
            Route::get('affiliates/{affiliate}', [Admin\AffiliateController::class, 'show'])->name('affiliates.show');
            Route::patch('affiliates/{affiliate}', [Admin\AffiliateController::class, 'update'])->name('affiliates.update');
            Route::delete('affiliates/{affiliate}', [Admin\AffiliateController::class, 'destroy'])->name('affiliates.destroy');
            Route::post('affiliates/{affiliate}/approve', [Admin\AffiliateController::class, 'approve'])->name('affiliates.approve');
            Route::post('affiliates/{affiliate}/deactivate', [Admin\AffiliateController::class, 'deactivate'])->name('affiliates.deactivate');
            Route::post('affiliates/payouts/{payout}/approve', [Admin\AffiliateController::class, 'approvePayout'])->name('affiliates.payouts.approve');
            Route::post('affiliates/referrals/{referral}/approve', [Admin\AffiliateController::class, 'approveReferral'])->name('affiliates.referrals.approve');
        });
    });

    // ── Client portal ─────────────────────────────────────────────────────────
    Route::prefix('client')->name('client.')->group(function () {
        Route::get('/', Client\DashboardController::class)->name('dashboard');
        Route::get('order', [Client\OrderController::class, 'catalog'])->name('order.catalog');
        Route::get('order/checkout', [Client\OrderController::class, 'checkout'])->name('order.checkout');
        Route::post('order', [Client\OrderController::class, 'place'])->name('order.place');
        Route::get('services', [Client\ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}', [Client\ServiceController::class, 'show'])->name('services.show');
        Route::post('services/{service}/cancel', [Client\ServiceController::class, 'requestCancellation'])->name('services.cancel');
        Route::post('services/{service}/upgrade', [Client\ServiceController::class, 'upgrade'])->name('services.upgrade');
        Route::post('services/{service}/addons', [Client\ServiceController::class, 'addAddon'])->name('services.addons.store');
        Route::get('invoices', [Client\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [Client\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/download', [Client\InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('invoices/{invoice}/checkout', [Client\PaymentController::class,              'checkout'])->name('invoices.checkout');
        Route::post('invoices/{invoice}/authorizenet', [Client\AuthorizeNetPaymentController::class,  'checkout'])->name('invoices.authorizenet.checkout');
        Route::post('invoices/{invoice}/apply-credit', [Client\InvoiceController::class,        'applyCredit'])->name('invoices.apply-credit');
        Route::post('invoices/{invoice}/paypal', [Client\PayPalPaymentController::class,  'checkout'])->name('invoices.paypal.checkout');
        Route::get('invoices/{invoice}/paypal/return', [Client\PayPalPaymentController::class,  'return'])->name('invoices.paypal.return');
        Route::get('invoices/{invoice}/paypal/cancel', [Client\PayPalPaymentController::class,  'cancel'])->name('invoices.paypal.cancel');
        Route::get('support', [Client\SupportController::class, 'index'])->name('support.index');
        Route::get('support/create', [Client\SupportController::class, 'create'])->name('support.create');
        Route::get('support/kb-suggest', [Client\SupportController::class, 'kbSuggest'])->name('support.kb-suggest');
        Route::post('support', [Client\SupportController::class, 'store'])->name('support.store');
        Route::get('support/attachments/{attachment}/download', [TicketAttachmentController::class, 'download'])->name('support.attachments.download');
        Route::get('support/{ticket}', [Client\SupportController::class, 'show'])->name('support.show');
        Route::post('support/{ticket}/reply', [Client\SupportController::class, 'reply'])->name('support.reply');
        Route::post('support/{ticket}/rate', [Client\SupportController::class, 'rate'])->name('support.rate');
        Route::get('announcements', Client\AnnouncementController::class)->name('announcements');
        Route::get('kb', [Client\KbController::class, 'index'])->name('kb.index');
        Route::get('kb/{kbArticle:slug}', [Client\KbController::class, 'show'])->name('kb.show');
        Route::get('domains', [Client\DomainController::class, 'index'])->name('domains.index');
        Route::get('domains/{domain}', [Client\DomainController::class, 'show'])->name('domains.show');
        Route::post('domains/{domain}/nameservers', [Client\DomainController::class, 'setNameservers'])->name('domains.nameservers');
        Route::post('domains/{domain}/auto-renew', [Client\DomainController::class, 'toggleAutoRenew'])->name('domains.auto-renew');
        Route::get('domains/check', [Client\DomainController::class, 'checkAvailability'])->name('domains.check');
        // Domain registration order flow
        Route::get('domain-order', [Client\DomainOrderController::class, 'search'])->name('domain-order.search');
        Route::get('domain-order/check', [Client\DomainOrderController::class, 'check'])->name('domain-order.check');
        Route::get('domain-order/checkout', [Client\DomainOrderController::class, 'checkout'])->name('domain-order.checkout');
        Route::post('domain-order', [Client\DomainOrderController::class, 'place'])->name('domain-order.place');
        // Domain transfer flow
        Route::get('domain-transfer', [Client\DomainTransferController::class, 'search'])->name('domain-transfer.search');
        Route::get('domain-transfer/checkout', [Client\DomainTransferController::class, 'checkout'])->name('domain-transfer.checkout');
        Route::post('domain-transfer', [Client\DomainTransferController::class, 'place'])->name('domain-transfer.place');
        Route::post('promo/validate', [Client\PromoController::class, 'validate'])->name('promo.validate');
        Route::get('quotes', [Client\QuoteController::class, 'index'])->name('quotes.index');
        Route::get('quotes/{quote}', [Client\QuoteController::class, 'show'])->name('quotes.show');
        Route::post('quotes/{quote}/accept', [Client\QuoteController::class, 'accept'])->name('quotes.accept');
        Route::post('quotes/{quote}/decline', [Client\QuoteController::class, 'decline'])->name('quotes.decline');

        // Affiliate
        Route::get('affiliate', [Client\AffiliateController::class, 'dashboard'])->name('affiliate.dashboard');
        Route::post('affiliate/register', [Client\AffiliateController::class, 'register'])->name('affiliate.register');
        Route::post('affiliate/payout', [Client\AffiliateController::class, 'requestPayout'])->name('affiliate.payout');

        // Payment Methods
        Route::get('payment-methods', [Client\PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::get('payment-methods/setup-intent', [Client\PaymentMethodController::class, 'setupIntent'])->name('payment-methods.setup-intent');
        Route::post('payment-methods', [Client\PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::post('payment-methods/{paymentMethod}/default', [Client\PaymentMethodController::class, 'setDefault'])->name('payment-methods.default');
        Route::delete('payment-methods/{paymentMethod}', [Client\PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
    });
});
