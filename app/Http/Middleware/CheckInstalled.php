<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckInstalled
{
    public function handle(Request $request, Closure $next): mixed
    {
        $installed = file_exists(storage_path('installed.lock'));

        // If not installed and not already heading to the installer, redirect there
        if (! $installed && ! $request->is('install', 'install/*')) {
            return redirect($request->getSchemeAndHttpHost().$request->getBaseUrl().'/install');
        }

        // If already installed and trying to access the installer, block it
        if ($installed && $request->is('install', 'install/*')) {
            abort(403, 'Strata is already installed.');
        }

        return $next($request);
    }
}
