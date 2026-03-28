<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AuditLogger;
use App\Services\OrderProvisioner;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $services = Service::with(['user', 'product'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) =>
                $q->where('domain', 'like', "%{$s}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")
                                                     ->orWhere('email', 'like', "%{$s}%"))
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Services/Index', [
            'services' => $services,
            'filters'  => $request->only('search', 'status'),
        ]);
    }

    public function show(Service $service): Response
    {
        $service->load(['user', 'product', 'invoiceItems.invoice', 'orderItem.order', 'serviceAddons.addon']);

        return Inertia::render('Admin/Services/Show', [
            'service'          => $service,
            'availableAddons'  => Addon::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'price', 'billing_cycle']),
        ]);
    }

    public function addAddon(Request $request, Service $service): RedirectResponse
    {
        $request->validate(['addon_id' => ['required', 'exists:addons,id']]);

        $addon = Addon::findOrFail($request->addon_id);

        DB::transaction(function () use ($service, $addon) {
            $sa = ServiceAddon::create([
                'service_id'    => $service->id,
                'addon_id'      => $addon->id,
                'status'        => 'active',
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
                        'service_id'      => $service->id,
                        'service_addon_id'=> $sa->id,
                        'description'     => "Setup Fee — {$addon->name}",
                        'quantity'        => 1,
                        'unit_price'      => $addon->setup_fee,
                        'total'           => $addon->setup_fee,
                    ]);
                }

                $inv->items()->create([
                    'service_id'      => $service->id,
                    'service_addon_id'=> $sa->id,
                    'description'     => $addon->name,
                    'quantity'        => 1,
                    'unit_price'      => $addon->price,
                    'total'           => $addon->price,
                ]);
            }
        });

        return back()->with('success', "Addon \"{$addon->name}\" added to service.");
    }

    public function removeAddon(Service $service, ServiceAddon $serviceAddon): RedirectResponse
    {
        abort_unless($serviceAddon->service_id === $service->id, 404);

        $serviceAddon->update(['status' => 'cancelled']);

        return back()->with('success', 'Addon cancelled.');
    }

    /**
     * Manually approve a pending service.
     * Runs the provisioner (if a module is configured) and sends the welcome email.
     */
    public function approve(Service $service): RedirectResponse
    {
        abort_unless($service->status === 'pending', 422);

        try {
            OrderProvisioner::provision($service);
        } catch (\Throwable $e) {
            return back()->with('error', 'Provisioning failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Service approved and activated.');
    }

    public function suspend(Service $service): RedirectResponse
    {
        $service->update(['status' => 'suspended']);

        AuditLogger::log('service.suspended', $service);
        WorkflowEngine::fire('service.suspended', $service);

        return back()->with('success', 'Service suspended.');
    }

    public function unsuspend(Service $service): RedirectResponse
    {
        $service->update(['status' => 'active']);

        return back()->with('success', 'Service reactivated.');
    }

    public function terminate(Service $service): RedirectResponse
    {
        $service->update([
            'status'           => 'terminated',
            'termination_date' => now(),
        ]);

        return back()->with('success', 'Service terminated.');
    }

    public function approveCancellation(Service $service): RedirectResponse
    {
        abort_unless($service->status === 'cancellation_requested', 422);

        if ($service->cancellation_type === 'end_of_period' && $service->next_due_date) {
            // Service stays active until the current period ends
            $service->update([
                'status'              => 'active',
                'scheduled_cancel_at' => $service->next_due_date,
            ]);

            return back()->with('success', "Cancellation approved — service will cancel on {$service->next_due_date->format('M d, Y')}.");
        }

        // Immediate cancellation
        $service->update([
            'status'           => 'cancelled',
            'termination_date' => now(),
        ]);

        AuditLogger::log('service.cancelled', $service);
        WorkflowEngine::fire('service.cancelled', $service);

        return back()->with('success', 'Cancellation approved — service cancelled.');
    }

    private function nextDueDate(string $cycle): string
    {
        $days = match ($cycle) {
            'monthly'     => 30,
            'quarterly'   => 91,
            'semi_annual' => 182,
            'annual'      => 365,
            'biennial'    => 730,
            'triennial'   => 1095,
            default       => 30,
        };

        return now()->addDays($days)->toDateString();
    }

    public function rejectCancellation(Service $service): RedirectResponse
    {
        abort_unless($service->status === 'cancellation_requested', 422);

        $service->update([
            'status'                    => 'active',
            'cancellation_reason'       => null,
            'cancellation_requested_at' => null,
            'cancellation_type'         => null,
            'scheduled_cancel_at'       => null,
        ]);

        return back()->with('success', 'Cancellation request rejected — service restored to active.');
    }
}
