<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAffiliateReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->filled('ref')) {
            $code = strtoupper(trim($request->input('ref')));

            if ($code) {
                cookie()->queue(
                    cookie('strata_ref', $code, 60 * 24 * 30) // 30-day cookie
                );
            }
        }

        return $next($request);
    }
}
