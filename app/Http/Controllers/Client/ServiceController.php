<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Service;
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
}
