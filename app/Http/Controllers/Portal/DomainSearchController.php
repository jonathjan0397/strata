<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TldPrice;
use App\Services\DomainRegistrarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => ['required', 'string', 'max:63', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/'],
        ]);

        $driver = Setting::get('integration_registrar_driver');
        if (! $driver) {
            return response()->json(['error' => 'Domain search is not configured.'], 503);
        }

        $sld = strtolower(trim($request->input('domain')));
        // Remove any accidentally submitted TLD
        if (str_contains($sld, '.')) {
            $sld = explode('.', $sld)[0];
        }

        $tldString = Setting::get('domain_search_tlds', '.com,.net,.org,.io');
        $tlds = array_filter(array_map(fn ($t) => trim($t), explode(',', $tldString)));

        $results = [];
        foreach ($tlds as $tld) {
            $tld = '.'.ltrim($tld, '.');
            $domain = $sld.$tld;
            try {
                $check = DomainRegistrarService::checkAvailability($domain);
                $tldPrice = TldPrice::where('tld', $tld)->where('is_active', true)->first();

                $results[] = [
                    'domain' => $domain,
                    'available' => $check['available'] ?? false,
                    'price' => $tldPrice ? $tldPrice->register_price : ($check['price'] ?? null),
                    'currency' => $tldPrice ? $tldPrice->currency : ($check['currency'] ?? 'USD'),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'domain' => $domain,
                    'available' => null,
                    'error' => 'Could not check availability.',
                ];
            }
        }

        return response()->json([
            'results' => $results,
            'register_url' => route('register'),
        ], 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        ]);
    }
}
