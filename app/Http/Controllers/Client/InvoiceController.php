<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Client/Invoices/Index', [
            'invoices' => $request->user()
                ->invoices()
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->load(['items.service', 'payments']);

        return Inertia::render('Client/Invoices/Show', ['invoice' => $invoice]);
    }
}
