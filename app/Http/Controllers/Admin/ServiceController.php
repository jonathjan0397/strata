<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
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
        $service->load(['user', 'product', 'invoiceItems.invoice']);

        return Inertia::render('Admin/Services/Show', ['service' => $service]);
    }

    public function suspend(Service $service): RedirectResponse
    {
        $service->update(['status' => 'suspended']);

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
}
