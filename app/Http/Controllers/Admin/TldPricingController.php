<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TldPrice;
use App\Services\DomainRegistrarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TldPricingController extends Controller
{
    public function index(): Response
    {
        $prices = TldPrice::orderBy('tld')->get()->map(fn ($t) => [
            'id' => $t->id,
            'tld' => $t->tld,
            'register_cost' => $t->register_cost,
            'renew_cost' => $t->renew_cost,
            'transfer_cost' => $t->transfer_cost,
            'markup_type' => $t->markup_type,
            'markup_value' => $t->markup_value,
            'register_price' => $t->register_price,
            'renew_price' => $t->renew_price,
            'transfer_price' => $t->transfer_price,
            'currency' => $t->currency,
            'is_active' => $t->is_active,
            'last_synced_at' => $t->last_synced_at?->toDateTimeString(),
        ]);

        try {
            $driver = config('registrars.default', 'namecheap');
            $canImport = method_exists(DomainRegistrarService::driver($driver), 'getPricing');
        } catch (\Throwable) {
            $canImport = false;
        }

        return Inertia::render('Admin/TldPricing/Index', [
            'prices' => $prices,
            'canImport' => $canImport,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tld' => ['required', 'string', 'max:32', 'regex:/^\.[a-z0-9.]+$/i'],
            'register_cost' => ['nullable', 'numeric', 'min:0'],
            'renew_cost' => ['nullable', 'numeric', 'min:0'],
            'transfer_cost' => ['nullable', 'numeric', 'min:0'],
            'markup_type' => ['required', 'in:fixed,percent'],
            'markup_value' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
        ]);

        $data['tld'] = '.'.ltrim($data['tld'], '.');

        TldPrice::updateOrCreate(['tld' => $data['tld']], $data);

        return back()->with('flash', ['success' => 'TLD pricing saved.']);
    }

    public function update(Request $request, TldPrice $tldPrice): RedirectResponse
    {
        $data = $request->validate([
            'register_cost' => ['nullable', 'numeric', 'min:0'],
            'renew_cost' => ['nullable', 'numeric', 'min:0'],
            'transfer_cost' => ['nullable', 'numeric', 'min:0'],
            'markup_type' => ['required', 'in:fixed,percent'],
            'markup_value' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
        ]);

        $tldPrice->update($data);

        return back()->with('flash', ['success' => 'TLD pricing updated.']);
    }

    public function destroy(TldPrice $tldPrice): RedirectResponse
    {
        $tldPrice->delete();

        return back()->with('flash', ['success' => 'TLD removed.']);
    }

    /** Apply a bulk action (markup / activate / deactivate / delete) to selected TLDs. */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids'          => ['required', 'array', 'min:1'],
            'ids.*'        => ['integer', 'exists:tld_pricing,id'],
            'action'       => ['required', 'in:markup,activate,deactivate,delete'],
            'markup_type'  => ['required_if:action,markup', 'nullable', 'in:fixed,percent'],
            'markup_value' => ['required_if:action,markup', 'nullable', 'numeric', 'min:0'],
        ]);

        $ids   = $data['ids'];
        $count = count($ids);

        match ($data['action']) {
            'markup'     => TldPrice::whereIn('id', $ids)->update([
                'markup_type'  => $data['markup_type'],
                'markup_value' => $data['markup_value'],
            ]),
            'activate'   => TldPrice::whereIn('id', $ids)->update(['is_active' => true]),
            'deactivate' => TldPrice::whereIn('id', $ids)->update(['is_active' => false]),
            'delete'     => TldPrice::whereIn('id', $ids)->delete(),
        };

        $label = match ($data['action']) {
            'markup'     => "Markup updated for {$count} TLD(s).",
            'activate'   => "{$count} TLD(s) activated.",
            'deactivate' => "{$count} TLD(s) deactivated.",
            'delete'     => "{$count} TLD(s) removed.",
        };

        return back()->with('flash', ['success' => $label]);
    }

    /** Import/sync prices from the active registrar API. */
    public function import(): RedirectResponse
    {
        try {
            $driver = DomainRegistrarService::driver();

            if (! method_exists($driver, 'getPricing')) {
                return back()->with('flash', ['error' => 'The active registrar does not support price importing.']);
            }

            $pricing = $driver->getPricing();

            if (empty($pricing)) {
                return back()->with('flash', ['error' => 'Registrar returned no pricing data.']);
            }

            $synced = 0;
            foreach ($pricing as $tld => $prices) {
                $tld = '.'.ltrim($tld, '.');
                $existing = TldPrice::where('tld', $tld)->first();

                if ($existing) {
                    $existing->update([
                        'register_cost' => $prices['register'] ?? $existing->register_cost,
                        'renew_cost' => $prices['renew'] ?? $existing->renew_cost,
                        'transfer_cost' => $prices['transfer'] ?? $existing->transfer_cost,
                        'currency' => $prices['currency'] ?? $existing->currency,
                        'last_synced_at' => now(),
                    ]);
                } else {
                    TldPrice::create([
                        'tld' => $tld,
                        'register_cost' => $prices['register'] ?? null,
                        'renew_cost' => $prices['renew'] ?? null,
                        'transfer_cost' => $prices['transfer'] ?? null,
                        'currency' => $prices['currency'] ?? 'USD',
                        'markup_type' => 'percent',
                        'markup_value' => 0,
                        'is_active' => true,
                        'last_synced_at' => now(),
                    ]);
                }
                $synced++;
            }

            return back()->with('flash', ['success' => "Imported pricing for {$synced} TLDs."]);

        } catch (\Throwable $e) {
            Log::error('TLD price import failed: '.$e->getMessage());

            return back()->with('flash', ['error' => 'Import failed: '.$e->getMessage()]);
        }
    }
}
