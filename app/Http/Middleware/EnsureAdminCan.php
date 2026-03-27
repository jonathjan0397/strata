<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminCan
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // Super-admin and admin always pass
        if ($user->hasRole(['super-admin', 'admin'])) {
            return $next($request);
        }

        // Staff must have the specific permission
        if ($user->hasRole('staff') && $user->hasPermissionTo($permission)) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access this area.');
    }
}
