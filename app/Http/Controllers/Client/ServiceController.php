<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\ClientCredit;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Client/Services/Index', [
            'services' => $request->user()
                ->services()
                ->with('product')
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, Service $service): Response
    {
        abort_unless($service->user_id === $request->user()->id, 403);

        $service->load(['product', 'invoiceItems.invoice', 'serviceAddons.addon']);

        // Products the client can upgrade/downgrade to: same type, not hidden, not current product
        $upgradableProducts = [];
        if ($service->status === 'active' && $service->product) {
            $upgradableProducts = Product::where('type', $service->product->type)
                ->where('hidden', false)
                ->where('id', '!=', $service->product_id)
                ->orderBy('price')
                ->get(['id', 'name', 'price', 'billing_cycle', 'short_description']);
        }

        $availableAddons = $service->status === 'active'
            ? Addon::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'price', 'setup_fee', 'billing_cycle', 'description'])
            : collect();

        return Inertia::render('Client/Services/Show', [
            'service'            => $service,
            'upgradableProducts' => $upgradableProducts,
            'availableAddons'    => $availableAddons,
        ]);
    }

    public function requestCancellation(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->user_id === $request->user()->id, 403);
        abort_if(in_array($service->status, ['cancelled', 'terminated']), 422);

        $request->validate([
            'reason'            => ['required', 'string', 'max:1000'],
            'cancellation_type' => ['required', 'in:immediate,end_of_period'],
        ]);

        $service->update([
            'status'                    => 'cancellation_requested',
            'cancellation_reason'       => $request->reason,
            'cancellation_requested_at' => now(),
            'cancellation_type'         => $request->cancellation_type,
        ]);

        return back()->with('success', 'Cancellation request submitted. Our team will process it shortly.');
    }

    public function upgrade(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->user_id === $request->user()->id, 403);
        abort_unless($service->status === 'active', 422);

        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $newProduct = Product::findOrFail($request->product_id);

        // Must be same product type and not the current product
        abort_if($newProduct->id === $service->product_id, 422);
        abort_if($newProduct->type !== $service->product?->type, 422);
        abort_if($newProduct->hidden, 404);

        $user = $request->user();

        DB::transaction(function () use ($service, $newProduct, $user) {
            $oldAmount  = (float) $service->amount;
            $newAmount  = (float) $newProduct->price;
            $cycledays  = $this->cycleDays($service->billing_cycle);
            $remaining  = max(0, now()->diffInDays($service->next_due_date, false));

            $dailyOld  = $cycledays > 0 ? ($oldAmount / $cycledays) : 0;
            $dailyNew  = $cycledays > 0 ? ($newAmount / $cycledays) : 0;
            $credit    = round($dailyOld * $remaining, 2);
            $charge    = round($dailyNew * $remaining, 2);
            $net       = round($charge - $credit, 2);

            // Update service to new product
            $service->update([
                'product_id'    => $newProduct->id,
                'amount'        => $newAmount,
                'billing_cycle' => $newProduct->billing_cycle,
            ]);

            if ($net > 0) {
                // Client owes more — create a prorated invoice
                $invoice = Invoice::create([
                    'user_id'    => $user->id,
                    'status'     => 'unpaid',
                    'subtotal'   => $net,
                    'tax_rate'   => 0,
                    'tax'        => 0,
                    'total'      => $net,
                    'amount_due' => $net,
                    'date'       => now()->toDateString(),
                    'due_date'   => now()->addDays(7)->toDateString(),
                    'notes'      => "Prorated upgrade: {$service->product?->name} → {$newProduct->name}",
                ]);

                $invoice->items()->create([
                    'service_id'  => $service->id,
                    'description' => "Upgrade: {$newProduct->name} (prorated {$remaining} days)",
                    'quantity'    => 1,
                    'unit_price'  => $net,
                    'total'       => $net,
                ]);
            } elseif ($net < 0) {
                // Client is downgrading — add prorated credit to their account
                $creditAmount = abs($net);

                ClientCredit::create([
                    'user_id'     => $user->id,
                    'amount'      => $creditAmount,
                    'description' => "Prorated credit: downgrade to {$newProduct->name}",
                ]);

                $user->increment('credit_balance', $creditAmount);
            }

            AuditLogger::log('service.upgraded', $service, [
                'old_product' => $service->product_id,
                'new_product' => $newProduct->id,
                'net'         => $net,
            ]);
        });

        return back()->with('success', 'Service plan updated. ' . (
            $request->has('product_id') ? 'Check your invoices if a prorated charge was applied.' : ''
        ));
    }

    public function addAddon(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->user_id === $request->user()->id, 403);
        abort_unless($service->status === 'active', 422);

        $request->validate(['addon_id' => ['required', 'exists:addons,id']]);

        $addon = Addon::findOrFail($request->addon_id);

        DB::transaction(function () use ($service, $addon) {
            $sa = ServiceAddon::create([
                'service_id'    => $service->id,
                'addon_id'      => $addon->id,
                'status'        => 'pending',
                'amount'        => $addon->price,
                'billing_cycle' => $addon->billing_cycle,
                'next_due_date' => $this->nextDueDate($addon->billing_cycle),
            ]);

            $lineTotal = (float) $addon->price + (float) $addon->setup_fee;
            if ($lineTotal > 0) {
                $inv = Invoice::create([
                    'user_id'    => $service->user_id,
                    'status'     => 'unpaid',
                    'subtotal'   => $lineTotal,
                    'tax_rate'   => 0,
                    'tax'        => 0,
                    'total'      => $lineTotal,
                    'amount_due' => $lineTotal,
                    'date'       => now()->toDateString(),
                    'due_date'   => now()->addDays(7)->toDateString(),
                    'notes'      => "Addon: {$addon->name} on service #{$service->id}",
                ]);

                if ((float) $addon->setup_fee > 0) {
                    $inv->items()->create([
                        'service_id'       => $service->id,
                        'service_addon_id' => $sa->id,
                        'description'      => "Setup Fee — {$addon->name}",
                        'quantity'         => 1,
                        'unit_price'       => $addon->setup_fee,
                        'total'            => $addon->setup_fee,
                    ]);
                }

                $inv->items()->create([
                    'service_id'       => $service->id,
                    'service_addon_id' => $sa->id,
                    'description'      => $addon->name,
                    'quantity'         => 1,
                    'unit_price'       => $addon->price,
                    'total'            => $addon->price,
                ]);
            }
        });

        return back()->with('success', "Addon \"{$addon->name}\" added. An invoice has been generated.");
    }

    /** Returns the number of days in a billing cycle for proration. */
    private function cycleDays(string $cycle): int
    {
        return match ($cycle) {
            'monthly'     => 30,
            'quarterly'   => 91,
            'semi_annual' => 182,
            'annual'      => 365,
            'biennial'    => 730,
            'triennial'   => 1095,
            default       => 30,
        };
    }

    private function nextDueDate(string $cycle): string
    {
        return now()->addDays($this->cycleDays($cycle))->toDateString();
    }
}
