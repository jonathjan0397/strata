<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Install\InstallerController;
use App\Http\Controllers\StripeWebhookController;
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
use App\Http\Controllers\Profile\SessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── Stripe webhook (CSRF exempt — verified by signature) ─────────────────────
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

// ── Installer (blocked by CheckInstalled once storage/installed.lock exists) ─
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/',              [InstallerController::class, 'index'])->name('index');
    Route::get('/requirements',  [InstallerController::class, 'requirements'])->name('requirements');
    Route::post('/test-database',[InstallerController::class, 'testDatabase'])->name('test-database');
    Route::post('/run',          [InstallerController::class, 'install'])->name('run');
});

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

    // Redirect root based on role
    Route::get('/', function () {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('client.dashboard');
    })->name('dashboard');

    // ── 2FA management ──────────────────────────────────────────────────────
    Route::post('user/two-factor-authentication',           [TwoFactorAuthenticationController::class, 'store'])->name('two-factor.enable');
    Route::get('user/two-factor-qr-code',                   [TwoFactorAuthenticationController::class, 'qrCode'])->name('two-factor.qr-code');
    Route::post('user/confirmed-two-factor-authentication', [TwoFactorAuthenticationController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('user/two-factor-authentication',         [TwoFactorAuthenticationController::class, 'destroy'])->name('two-factor.disable');

    // ── Profile ──────────────────────────────────────────────────────────────
    Route::get('profile/security', fn () => Inertia::render('Profile/Security'))->name('profile.security');
    Route::get('profile/sessions',                [SessionController::class, 'index'])->name('profile.sessions');
    Route::delete('profile/sessions/{session}',   [SessionController::class, 'destroy'])->name('profile.sessions.destroy');
    Route::delete('profile/sessions',             [SessionController::class, 'destroyOthers'])->name('profile.sessions.destroy-others');

    // ── Admin panel ──────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/',         Admin\DashboardController::class)->name('dashboard');

        // Clients
        Route::get('clients',              [Admin\ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create',       [Admin\ClientController::class, 'create'])->name('clients.create');
        Route::post('clients',             [Admin\ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}',     [Admin\ClientController::class, 'show'])->name('clients.show');
        Route::patch('clients/{client}',   [Admin\ClientController::class, 'update'])->name('clients.update');
        Route::post('clients/{client}/suspend', [Admin\ClientController::class, 'suspend'])->name('clients.suspend');

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
        Route::post('services/{service}/suspend',   [Admin\ServiceController::class, 'suspend'])->name('services.suspend');
        Route::post('services/{service}/unsuspend', [Admin\ServiceController::class, 'unsuspend'])->name('services.unsuspend');
        Route::post('services/{service}/terminate', [Admin\ServiceController::class, 'terminate'])->name('services.terminate');

        // Invoices
        Route::get('invoices',             [Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create',      [Admin\InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices',            [Admin\InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}',          [Admin\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/download',  [Admin\InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('invoices/{invoice}/mark-paid',[Admin\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
        Route::post('invoices/{invoice}/cancel',    [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');

        // Support
        Route::get('support',              [Admin\SupportController::class, 'index'])->name('support.index');
        Route::get('support/{ticket}',     [Admin\SupportController::class, 'show'])->name('support.show');
        Route::post('support/{ticket}/reply',  [Admin\SupportController::class, 'reply'])->name('support.reply');
        Route::post('support/{ticket}/assign', [Admin\SupportController::class, 'assign'])->name('support.assign');
        Route::post('support/{ticket}/close',  [Admin\SupportController::class, 'close'])->name('support.close');
        Route::post('support/{ticket}/reopen', [Admin\SupportController::class, 'reopen'])->name('support.reopen');

        // Settings
        Route::get('settings',  [Admin\SettingController::class, 'index'])->name('settings.index');
        Route::patch('settings', [Admin\SettingController::class, 'update'])->name('settings.update');

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
    });

    // ── Client portal ─────────────────────────────────────────────────────────
    Route::prefix('client')->name('client.')->group(function () {
        Route::get('/',                          Client\DashboardController::class)->name('dashboard');
        Route::get('order',                      [Client\OrderController::class, 'catalog'])->name('order.catalog');
        Route::get('order/checkout',             [Client\OrderController::class, 'checkout'])->name('order.checkout');
        Route::post('order',                     [Client\OrderController::class, 'place'])->name('order.place');
        Route::get('services',                   [Client\ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}',         [Client\ServiceController::class, 'show'])->name('services.show');
        Route::get('invoices',                       [Client\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}',             [Client\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/download',    [Client\InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('invoices/{invoice}/checkout',        [Client\PaymentController::class,       'checkout'])->name('invoices.checkout');
        Route::post('invoices/{invoice}/paypal',          [Client\PayPalPaymentController::class,  'checkout'])->name('invoices.paypal.checkout');
        Route::get('invoices/{invoice}/paypal/return',    [Client\PayPalPaymentController::class,  'return'])->name('invoices.paypal.return');
        Route::get('invoices/{invoice}/paypal/cancel',    [Client\PayPalPaymentController::class,  'cancel'])->name('invoices.paypal.cancel');
        Route::get('support',                    [Client\SupportController::class, 'index'])->name('support.index');
        Route::get('support/create',             [Client\SupportController::class, 'create'])->name('support.create');
        Route::post('support',                   [Client\SupportController::class, 'store'])->name('support.store');
        Route::get('support/{ticket}',           [Client\SupportController::class, 'show'])->name('support.show');
        Route::post('support/{ticket}/reply',    [Client\SupportController::class, 'reply'])->name('support.reply');
        Route::get('announcements',              Client\AnnouncementController::class)->name('announcements');
        Route::get('domains',                    [Client\DomainController::class, 'index'])->name('domains.index');
        Route::get('domains/{domain}',           [Client\DomainController::class, 'show'])->name('domains.show');
        Route::post('domains/{domain}/nameservers', [Client\DomainController::class, 'setNameservers'])->name('domains.nameservers');
        Route::post('domains/{domain}/auto-renew',  [Client\DomainController::class, 'toggleAutoRenew'])->name('domains.auto-renew');
        Route::get('domains/check',              [Client\DomainController::class, 'checkAvailability'])->name('domains.check');
    });
});
