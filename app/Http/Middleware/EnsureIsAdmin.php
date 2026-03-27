<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
