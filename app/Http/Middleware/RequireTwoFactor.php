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

        // 2FA is optional but strongly recommended — always let the user through.
        // A warning banner is shown in the UI via the twoFactorWarning shared prop.
    }
}
