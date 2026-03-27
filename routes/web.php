<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Profile\SessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── Guest ────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login',  [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password',  [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password',        [NewPasswordController::class, 'store'])->name('password.store');
});

// ── 2FA challenge (between guest and auth) ───────────────────────────────────
Route::get('two-factor-challenge',  [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);

// ── Authenticated (email not required yet) ───────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Email verification
    Route::get('email/verify',                  EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('email/verify/{id}/{hash}',       VerifyEmailController::class)->middleware('signed')->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->middleware('throttle:6,1')->name('verification.send');
});

// ── Authenticated + email verified ───────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');

    // 2FA management (profile)
    Route::post('user/two-factor-authentication',           [TwoFactorAuthenticationController::class, 'store'])->name('two-factor.enable');
    Route::get('user/two-factor-qr-code',                   [TwoFactorAuthenticationController::class, 'qrCode'])->name('two-factor.qr-code');
    Route::post('user/confirmed-two-factor-authentication', [TwoFactorAuthenticationController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('user/two-factor-authentication',         [TwoFactorAuthenticationController::class, 'destroy'])->name('two-factor.disable');

    // Profile
    Route::get('profile/security', fn () => Inertia::render('Profile/Security'))->name('profile.security');
    Route::get('profile/sessions',                [SessionController::class, 'index'])->name('profile.sessions');
    Route::delete('profile/sessions/{session}',   [SessionController::class, 'destroy'])->name('profile.sessions.destroy');
    Route::delete('profile/sessions',             [SessionController::class, 'destroyOthers'])->name('profile.sessions.destroy-others');
});
