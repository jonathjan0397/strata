<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\AuditLogger;
use App\Services\OrderProvisioner;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $service->load(['user', 'product', 'invoiceItems.invoice', 'orderItem.order']);

        return Inertia::render('Admin/Services/Show', ['service' => $service]);
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

        $service->update([
            'status'           => 'cancelled',
            'termination_date' => now(),
        ]);

        AuditLogger::log('service.cancelled', $service);
        WorkflowEngine::fire('service.cancelled', $service);

        return back()->with('success', 'Cancellation approved — service cancelled.');
    }

    public function rejectCancellation(Service $service): RedirectResponse
    {
        abort_unless($service->status === 'cancellation_requested', 422);

        $service->update([
            'status'                    => 'active',
            'cancellation_reason'       => null,
            'cancellation_requested_at' => null,
        ]);

        return back()->with('success', 'Cancellation request rejected — service restored to active.');
    }
}
