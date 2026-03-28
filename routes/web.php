<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Install\InstallerController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TicketAttachmentController;
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
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\WidgetController;
use App\Http\Controllers\Profile\SessionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
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

// ── Installer (blocked by CheckInstalled once storage/installed.lock exists) ─
// Session/cookie/CSRF middleware are stripped so no laravel-session cookie is
// ever issued during install — prevents ModSecurity false-positive 403s on
// shared hosting where the encrypted cookie value triggers SQL-injection rules.
Route::prefix('install')->name('install.')->withoutMiddleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
])->group(function () {
    Route::get('/',              [InstallerController::class, 'index'])->name('index');
    Route::get('/requirements',  [InstallerController::class, 'requirements'])->name('requirements');
    Route::post('/test-database',[InstallerController::class, 'testDatabase'])->name('test-database');
    Route::post('/run',          [InstallerController::class, 'install'])->name('run');
});

// ── Public portal (no auth required) ─────────────────────────────────────────
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/',              [PortalController::class, 'home'])->name('home');
    Route::get('/services',      [PortalController::class, 'products'])->name('portal.products');
    Route::get('/kb',            [PortalController::class, 'kb'])->name('portal.kb');
    Route::get('/kb/{slug}',     [PortalController::class, 'kbArticle'])->name('portal.kb.show');
    Route::get('/announcements', [PortalController::class, 'announcements'])->name('portal.announcements');
});

// ── Embeddable widget API (JSON, CORS-open, read-only) ────────────────────────
Route::middleware('throttle:60,1')->prefix('api/widget')->name('widget.')->group(function () {
    Route::get('products',      [WidgetController::class, 'products'])->name('products');
    Route::get('announcements', [WidgetController::class, 'announcements'])->name('announcements');
    Route::get('kb',            [WidgetController::class, 'kb'])->name('kb');
});

Route::get('strata-widget.js', [WidgetController::class, 'widgetJs'])->name('widget.js');

// ── Guest ────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login',   [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login',  [AuthenticatedSessionController::class, 'store']);
    Route::get('register',  [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('forgot-password',  [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password',        [NewPasswordController::class, 'store'])->name('password.store');
});

// ── OAuth2 ───────────────────────────────────────────────────────────────────
Route::get('auth/{provider}/redirect', [SocialiteController::class, 'redirect'])->name('socialite.redirect');
Route::get('auth/{provider}/callback', [SocialiteController::class, 'callback'])->name('socialite.callback');

// ── 2FA challenge ────────────────────────────────────────────────────────────
Route::get('two-factor-challenge',  [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);

// ── Auth (no verified requirement) ───────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('email/verify',                      EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('email/verify/{id}/{hash}',           VerifyEmailController::class)->middleware('signed')->name('verification.verify');
    Route::post('email/verification-notification',  [EmailVerificationNotificationController::class, 'store'])->middleware('throttle:6,1')->name('verification.send');
});

// ── Auth + Verified ───────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // Root handled by public portal controller (redirects if logged in)

    // ── 2FA management ──────────────────────────────────────────────────────
    Route::post('user/two-factor-authentication',           [TwoFactorAuthenticationController::class, 'store'])->name('two-factor.enable');
    Route::get('user/two-factor-qr-code',                   [TwoFactorAuthenticationController::class, 'qrCode'])->name('two-factor.qr-code');
    Route::post('user/confirmed-two-factor-authentication', [TwoFactorAuthenticationController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('user/two-factor-authentication',         [TwoFactorAuthenticationController::class, 'destroy'])->name('two-factor.disable');

    // ── Profile ──────────────────────────────────────────────────────────────
    Route::get('profile',                         [ProfileController::class,  'edit'])->name('profile.edit');
    Route::patch('profile',                       [ProfileController::class,  'update'])->name('profile.update');
    Route::get('profile/security', fn () => Inertia::render('Profile/Security'))->name('profile.security');
    Route::get('profile/sessions',                [SessionController::class, 'index'])->name('profile.sessions');
    Route::delete('profile/sessions/{session}',   [SessionController::class, 'destroy'])->name('profile.sessions.destroy');
    Route::delete('profile/sessions',             [SessionController::class, 'destroyOthers'])->name('profile.sessions.destroy-others');

    // ── Admin panel ──────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->middleware(['admin', 'require.2fa'])->group(function () {
        Route::get('/',         Admin\DashboardController::class)->name('dashboard');

        // Clients
        Route::get('clients',              [Admin\ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create',       [Admin\ClientController::class, 'create'])->name('clients.create');
        Route::post('clients',             [Admin\ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}',     [Admin\ClientController::class, 'show'])->name('clients.show');
        Route::patch('clients/{client}',   [Admin\ClientController::class, 'update'])->name('clients.update');
        Route::post('clients/{client}/suspend',              [Admin\ClientController::class, 'suspend'])->name('clients.suspend');
        Route::post('clients/{client}/credit',               [Admin\ClientController::class, 'addCredit'])->name('clients.credit');
        Route::post('clients/{client}/notes',                [Admin\ClientController::class, 'storeNote'])->name('clients.notes.store');
        Route::delete('clients/{client}/notes/{note}',       [Admin\ClientController::class, 'destroyNote'])->name('clients.notes.destroy');
        Route::post('clients/{client}/email',                [Admin\ClientController::class, 'sendEmail'])->name('clients.email');
        Route::post('clients/{client}/tasks',                [Admin\ClientController::class, 'storeTask'])->name('clients.tasks.store');
        Route::patch('clients/{client}/tasks/{task}/complete',[Admin\ClientController::class, 'completeTask'])->name('clients.tasks.complete');
        Route::delete('clients/{client}/tasks/{task}',       [Admin\ClientController::class, 'destroyTask'])->name('clients.tasks.destroy');

        Route::get('client-groups',                          [Admin\ClientGroupController::class, 'index'])->name('client-groups.index');
        Route::post('client-groups',                         [Admin\ClientGroupController::class, 'store'])->name('client-groups.store');
        Route::patch('client-groups/{clientGroup}',          [Admin\ClientGroupController::class, 'update'])->name('client-groups.update');
        Route::delete('client-groups/{clientGroup}',         [Admin\ClientGroupController::class, 'destroy'])->name('client-groups.destroy');
        Route::post('clients/{client}/assign-group',         [Admin\ClientGroupController::class, 'assignClient'])->name('client-groups.assign');

        // Promo Codes
        Route::get('promo-codes',               [Admin\PromoCodeController::class, 'index'])->name('promo-codes.index');
        Route::get('promo-codes/create',        [Admin\PromoCodeController::class, 'create'])->name('promo-codes.create');
        Route::post('promo-codes',              [Admin\PromoCodeController::class, 'store'])->name('promo-codes.store');
        Route::get('promo-codes/{promoCode}/edit', [Admin\PromoCodeController::class, 'edit'])->name('promo-codes.edit');
        Route::patch('promo-codes/{promoCode}', [Admin\PromoCodeController::class, 'update'])->name('promo-codes.update');
        Route::delete('promo-codes/{promoCode}',[Admin\PromoCodeController::class, 'destroy'])->name('promo-codes.destroy');

        // Products
        Route::get('products',             [Admin\ProductController::class, 'index'])->name('products.index');
        Route::get('products/create',      [Admin\ProductController::class, 'create'])->name('products.create');
        Route::post('products',            [Admin\ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit',  [Admin\ProductController::class, 'edit'])->name('products.edit');
        Route::patch('products/{product}', [Admin\ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}',[Admin\ProductController::class, 'destroy'])->name('products.destroy');

        // Services
        Route::get('services',             [Admin\ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}',   [Admin\ServiceController::class, 'show'])->name('services.show');
        Route::post('services/{service}/approve',             [Admin\ServiceController::class, 'approve'])->name('services.approve');
        Route::post('services/{service}/suspend',             [Admin\ServiceController::class, 'suspend'])->name('services.suspend');
        Route::post('services/{service}/unsuspend',           [Admin\ServiceController::class, 'unsuspend'])->name('services.unsuspend');
        Route::post('services/{service}/terminate',           [Admin\ServiceController::class, 'terminate'])->name('services.terminate');
        Route::post('services/{service}/approve-cancellation',[Admin\ServiceController::class, 'approveCancellation'])->name('services.approve-cancellation');
        Route::post('services/{service}/reject-cancellation', [Admin\ServiceController::class, 'rejectCancellation'])->name('services.reject-cancellation');

        // Invoices
        Route::get('invoices',             [Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create',      [Admin\InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices',            [Admin\InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}',          [Admin\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/download',  [Admin\InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('invoices/{invoice}/mark-paid',[Admin\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
        Route::post('invoices/{invoice}/cancel',    [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/send',      [Admin\InvoiceController::class, 'sendEmail'])->name('invoices.send');

        // Support
        Route::get('support',                            [Admin\SupportController::class, 'index'])->name('support.index');
        Route::post('support/bulk',                      [Admin\SupportController::class, 'bulkAction'])->name('support.bulk');
        Route::get('support/attachments/{attachment}/download', [TicketAttachmentController::class, 'download'])->name('support.attachments.download');
        Route::get('support/{ticket}',                   [Admin\SupportController::class, 'show'])->name('support.show');
        Route::post('support/{ticket}/reply',            [Admin\SupportController::class, 'reply'])->name('support.reply');
        Route::post('support/{ticket}/assign',           [Admin\SupportController::class, 'assign'])->name('support.assign');
        Route::post('support/{ticket}/close',            [Admin\SupportController::class, 'close'])->name('support.close');
        Route::post('support/{ticket}/reopen',           [Admin\SupportController::class, 'reopen'])->name('support.reopen');
        Route::patch('support/{ticket}/priority',        [Admin\SupportController::class, 'setPriority'])->name('support.priority');
        Route::patch('support/{ticket}/department',      [Admin\SupportController::class, 'transferDepartment'])->name('support.transfer');
        Route::post('support/{ticket}/merge',            [Admin\SupportController::class, 'merge'])->name('support.merge');

        // Settings
        Route::get('settings',                    [Admin\SettingController::class, 'index'])->name('settings.index');
        Route::patch('settings',                  [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::patch('settings/mail',             [Admin\SettingController::class, 'updateMail'])->name('settings.mail');
        Route::post('settings/mail/test',         [Admin\SettingController::class, 'testMail'])->name('settings.mail.test');
        Route::post('settings/logo',              [Admin\SettingController::class, 'uploadLogo'])->name('settings.logo');
        Route::patch('settings/integrations',     [Admin\SettingController::class, 'updateIntegrations'])->name('settings.integrations');

        // Maintenance — run pending database migrations (super-admin only)
        Route::post('maintenance/migrate', function () {
            abort_unless(auth()->user()?->hasRole('super-admin'), 403);
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output = trim(\Illuminate\Support\Facades\Artisan::output()) ?: 'Nothing to migrate.';
            return response()->json(['success' => true, 'output' => $output]);
        })->name('maintenance.migrate');

        // Departments
        Route::get('settings/departments',                [Admin\DepartmentController::class, 'index'])->name('departments.index');
        Route::post('settings/departments',               [Admin\DepartmentController::class, 'store'])->name('departments.store');
        Route::patch('settings/departments/{department}', [Admin\DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('settings/departments/{department}',[Admin\DepartmentController::class, 'destroy'])->name('departments.destroy');

        // Canned Responses
        Route::get('settings/canned-responses',                       [Admin\CannedResponseController::class, 'index'])->name('canned-responses.index');
        Route::post('settings/canned-responses',                      [Admin\CannedResponseController::class, 'store'])->name('canned-responses.store');
        Route::patch('settings/canned-responses/{cannedResponse}',    [Admin\CannedResponseController::class, 'update'])->name('canned-responses.update');
        Route::delete('settings/canned-responses/{cannedResponse}',   [Admin\CannedResponseController::class, 'destroy'])->name('canned-responses.destroy');

        // Knowledge Base (admin)
        Route::get('kb',                                      [Admin\KbController::class, 'index'])->name('kb.index');
        Route::get('kb/categories',                           [Admin\KbController::class, 'categories'])->name('kb.categories');
        Route::post('kb/categories',                          [Admin\KbController::class, 'storeCategory'])->name('kb.categories.store');
        Route::patch('kb/categories/{kbCategory}',            [Admin\KbController::class, 'updateCategory'])->name('kb.categories.update');
        Route::delete('kb/categories/{kbCategory}',           [Admin\KbController::class, 'destroyCategory'])->name('kb.categories.destroy');
        Route::get('kb/create',                               [Admin\KbController::class, 'create'])->name('kb.create');
        Route::post('kb',                                     [Admin\KbController::class, 'store'])->name('kb.store');
        Route::get('kb/{kbArticle}/edit',                     [Admin\KbController::class, 'edit'])->name('kb.edit');
        Route::patch('kb/{kbArticle}',                        [Admin\KbController::class, 'update'])->name('kb.update');
        Route::delete('kb/{kbArticle}',                       [Admin\KbController::class, 'destroy'])->name('kb.destroy');
        Route::post('kb/images',                              [Admin\KbController::class, 'uploadImage'])->name('kb.images.upload');

        // Domains
        Route::get('domains',                           [Admin\DomainController::class, 'index'])->name('domains.index');
        Route::get('domains/{domain}',                  [Admin\DomainController::class, 'show'])->name('domains.show');
        Route::post('domains/{domain}/nameservers',     [Admin\DomainController::class, 'syncNameservers'])->name('domains.nameservers');
        Route::post('domains/{domain}/lock',            [Admin\DomainController::class, 'setLock'])->name('domains.lock');
        Route::post('domains/{domain}/privacy',         [Admin\DomainController::class, 'setPrivacy'])->name('domains.privacy');
        Route::post('domains/{domain}/refresh',         [Admin\DomainController::class, 'refresh'])->name('domains.refresh');

        // Modules / Servers
        Route::get('modules',              [Admin\ModuleController::class, 'index'])->name('modules.index');
        Route::get('modules/create',       [Admin\ModuleController::class, 'create'])->name('modules.create');
        Route::post('modules',             [Admin\ModuleController::class, 'store'])->name('modules.store');
        Route::get('modules/{module}/edit',    [Admin\ModuleController::class, 'edit'])->name('modules.edit');
        Route::patch('modules/{module}',   [Admin\ModuleController::class, 'update'])->name('modules.update');
        Route::delete('modules/{module}',  [Admin\ModuleController::class, 'destroy'])->name('modules.destroy');

        // Email Templates
        Route::get('email-templates',                        [Admin\EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('email-templates/{emailTemplate}/edit',   [Admin\EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::patch('email-templates/{emailTemplate}',      [Admin\EmailTemplateController::class, 'update'])->name('email-templates.update');

        // Announcements
        Route::get('announcements',                    [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('announcements/create',             [Admin\AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('announcements',                   [Admin\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('announcements/{announcement}/edit',[Admin\AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::patch('announcements/{announcement}',   [Admin\AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}',  [Admin\AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        // Team (admin + staff) management
        Route::get('staff',                    [Admin\StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create',             [Admin\StaffController::class, 'create'])->name('staff.create');
        Route::post('staff',                   [Admin\StaffController::class, 'store'])->name('staff.store');
        Route::get('staff/{staff}/edit',       [Admin\StaffController::class, 'edit'])->name('staff.edit');
        Route::patch('staff/{staff}',          [Admin\StaffController::class, 'update'])->name('staff.update');
        Route::delete('staff/{staff}',         [Admin\StaffController::class, 'destroy'])->name('staff.destroy');

        // Audit Log
        Route::get('audit-log',                [Admin\AuditLogController::class, 'index'])->name('audit-log.index');

        // Workflows (Premium ⭐)
        Route::get('workflows',                         [Admin\WorkflowController::class, 'index'])->name('workflows.index');
        Route::get('workflows/create',                  [Admin\WorkflowController::class, 'create'])->name('workflows.create');
        Route::post('workflows',                        [Admin\WorkflowController::class, 'store'])->name('workflows.store');
        Route::get('workflows/{workflow}/edit',         [Admin\WorkflowController::class, 'edit'])->name('workflows.edit');
        Route::patch('workflows/{workflow}',            [Admin\WorkflowController::class, 'update'])->name('workflows.update');
        Route::delete('workflows/{workflow}',           [Admin\WorkflowController::class, 'destroy'])->name('workflows.destroy');
        Route::post('workflows/{workflow}/toggle',      [Admin\WorkflowController::class, 'toggleActive'])->name('workflows.toggle');

        Route::get('reports',                  [Admin\ReportController::class, 'index'])->name('reports.index');

        Route::get('tax-rates',                [Admin\TaxRateController::class, 'index'])->name('tax-rates.index');
        Route::post('tax-rates',               [Admin\TaxRateController::class, 'store'])->name('tax-rates.store');
        Route::patch('tax-rates/{taxRate}',    [Admin\TaxRateController::class, 'update'])->name('tax-rates.update');
        Route::delete('tax-rates/{taxRate}',   [Admin\TaxRateController::class, 'destroy'])->name('tax-rates.destroy');

        Route::get('email-log',                [Admin\EmailLogController::class, 'index'])->name('email-log.index');
        Route::get('email-log/{emailLog}',     [Admin\EmailLogController::class, 'show'])->name('email-log.show');
    });

    // ── Client portal ─────────────────────────────────────────────────────────
    Route::prefix('client')->name('client.')->group(function () {
        Route::get('/',                          Client\DashboardController::class)->name('dashboard');
        Route::get('order',                      [Client\OrderController::class, 'catalog'])->name('order.catalog');
        Route::get('order/checkout',             [Client\OrderController::class, 'checkout'])->name('order.checkout');
        Route::post('order',                     [Client\OrderController::class, 'place'])->name('order.place');
        Route::get('services',                            [Client\ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}',                  [Client\ServiceController::class, 'show'])->name('services.show');
        Route::post('services/{service}/cancel',          [Client\ServiceController::class, 'requestCancellation'])->name('services.cancel');
        Route::post('services/{service}/upgrade',         [Client\ServiceController::class, 'upgrade'])->name('services.upgrade');
        Route::get('invoices',                       [Client\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}',             [Client\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/download',    [Client\InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('invoices/{invoice}/checkout',        [Client\PaymentController::class,              'checkout'])->name('invoices.checkout');
    Route::post('invoices/{invoice}/authorizenet',    [Client\AuthorizeNetPaymentController::class,  'checkout'])->name('invoices.authorizenet.checkout');
        Route::post('invoices/{invoice}/apply-credit',    [Client\InvoiceController::class,        'applyCredit'])->name('invoices.apply-credit');
        Route::post('invoices/{invoice}/paypal',          [Client\PayPalPaymentController::class,  'checkout'])->name('invoices.paypal.checkout');
        Route::get('invoices/{invoice}/paypal/return',    [Client\PayPalPaymentController::class,  'return'])->name('invoices.paypal.return');
        Route::get('invoices/{invoice}/paypal/cancel',    [Client\PayPalPaymentController::class,  'cancel'])->name('invoices.paypal.cancel');
        Route::get('support',                    [Client\SupportController::class, 'index'])->name('support.index');
        Route::get('support/create',             [Client\SupportController::class, 'create'])->name('support.create');
        Route::post('support',                   [Client\SupportController::class, 'store'])->name('support.store');
        Route::get('support/attachments/{attachment}/download', [TicketAttachmentController::class, 'download'])->name('support.attachments.download');
        Route::get('support/{ticket}',           [Client\SupportController::class, 'show'])->name('support.show');
        Route::post('support/{ticket}/reply',    [Client\SupportController::class, 'reply'])->name('support.reply');
        Route::post('support/{ticket}/rate',     [Client\SupportController::class, 'rate'])->name('support.rate');
        Route::get('announcements',              Client\AnnouncementController::class)->name('announcements');
        Route::get('kb',                         [Client\KbController::class, 'index'])->name('kb.index');
        Route::get('kb/{kbArticle:slug}',        [Client\KbController::class, 'show'])->name('kb.show');
        Route::get('domains',                    [Client\DomainController::class, 'index'])->name('domains.index');
        Route::get('domains/{domain}',           [Client\DomainController::class, 'show'])->name('domains.show');
        Route::post('domains/{domain}/nameservers', [Client\DomainController::class, 'setNameservers'])->name('domains.nameservers');
        Route::post('domains/{domain}/auto-renew',  [Client\DomainController::class, 'toggleAutoRenew'])->name('domains.auto-renew');
        Route::get('domains/check',              [Client\DomainController::class, 'checkAvailability'])->name('domains.check');
        Route::post('promo/validate',            [Client\PromoController::class, 'validate'])->name('promo.validate');

        // Payment Methods
        Route::get('payment-methods',                              [Client\PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::get('payment-methods/setup-intent',                 [Client\PaymentMethodController::class, 'setupIntent'])->name('payment-methods.setup-intent');
        Route::post('payment-methods',                             [Client\PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::post('payment-methods/{paymentMethod}/default',     [Client\PaymentMethodController::class, 'setDefault'])->name('payment-methods.default');
        Route::delete('payment-methods/{paymentMethod}',           [Client\PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
    });
});
