<?php

namespace App\Http\Controllers\Client;

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

        $service->load(['product', 'invoiceItems.invoice']);

        return Inertia::render('Client/Services/Show', ['service' => $service]);
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
}
