<?php

use App\Http\Middleware\CheckInstalled;
use App\Http\Middleware\EnsureAdminCan;
use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireFeature;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\TrackAffiliateReferral;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            CheckInstalled::class,
            TrackAffiliateReferral::class,
        ]);
        $middleware->alias([
            'admin'           => EnsureIsAdmin::class,
            'admin.can'       => EnsureAdminCan::class,
            'require.2fa'     => RequireTwoFactor::class,
            'require.feature' => RequireFeature::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'pipe/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
