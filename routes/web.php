<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
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

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');
});
