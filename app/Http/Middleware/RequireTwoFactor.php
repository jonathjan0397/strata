<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    /**
     * Enforce 2FA for admin and staff roles.
     * If they don't have a confirmed 2FA secret, redirect to the security page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(['super-admin', 'admin', 'staff'])) {
            return $next($request);
        }

        // Already passed 2FA or has it confirmed
        if ($user->two_factor_enabled && $user->two_factor_confirmed_at) {
            return $next($request);
        }

        // Allow the security page and 2FA-related routes through so they can set it up
        if ($request->routeIs('profile.security', 'two-factor.*')) {
            return $next($request);
        }

        return redirect()->route('profile.security')->with(
            'error',
            'You must enable two-factor authentication before accessing the admin panel.'
        );
    }
}
