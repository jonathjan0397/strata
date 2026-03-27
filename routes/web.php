<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('login',          [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login',         [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password',  [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password',        [NewPasswordController::class, 'store'])->name('password.store');
});

// 2FA challenge (no guest/auth middleware — sits between the two)
Route::get('two-factor-challenge',  [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');

    // 2FA management (profile)
    Route::post('user/two-factor-authentication',            [TwoFactorAuthenticationController::class, 'store'])->name('two-factor.enable');
    Route::get('user/two-factor-qr-code',                    [TwoFactorAuthenticationController::class, 'qrCode'])->name('two-factor.qr-code');
    Route::post('user/confirmed-two-factor-authentication',  [TwoFactorAuthenticationController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('user/two-factor-authentication',          [TwoFactorAuthenticationController::class, 'destroy'])->name('two-factor.disable');

    // Security settings page
    Route::get('profile/security', fn () => Inertia::render('Profile/Security'))->name('profile.security');
});
