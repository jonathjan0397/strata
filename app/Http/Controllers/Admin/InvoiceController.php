<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Mail\TemplateMailable;
use App\Services\AuditLogger;
use App\Services\WorkflowEngine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $invoices = Invoice::with('user')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) =>
                $q->whereHas('user', fn ($u) =>
                    $u->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%")
                )
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $invoices,
            'filters'  => $request->only('search', 'status'),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load(['user', 'items.service.product', 'payments']);

        return Inertia::render('Admin/Invoices/Show', ['invoice' => $invoice]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Invoices/Create', [
            'clients' => User::role('client')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id'  => ['required', 'exists:users,id'],
            'due_date' => ['required', 'date'],
            'items'    => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
        ]);

        $subtotal = collect($request->items)
            ->sum(fn ($i) => $i['quantity'] * $i['unit_price']);

        $invoice = Invoice::create([
            'user_id'    => $request->user_id,
            'status'     => 'unpaid',
            'subtotal'   => $subtotal,
            'total'      => $subtotal,
            'amount_due' => $subtotal,
            'date'       => now(),
            'due_date'   => $request->due_date,
        ]);

        foreach ($request->items as $item) {
            $invoice->items()->create([
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'total'       => $item['quantity'] * $item['unit_price'],
            ]);
        }

        AuditLogger::log('invoice.created', $invoice);
        WorkflowEngine::fire('invoice.created', $invoice);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', "Invoice #{$invoice->id} created.");
    }

    public function download(Invoice $invoice): HttpResponse
    {
        $invoice->load(['user', 'items', 'payments']);

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("invoice-{$invoice->id}.pdf");
    }

    public function markPaid(Invoice $invoice): RedirectResponse
    {
        $invoice->load('user');

        $invoice->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        Mail::to($invoice->user->email)->queue(new TemplateMailable('invoice.paid', [
            'name'        => $invoice->user->name,
            'app_name'    => config('app.name'),
            'invoice_id'  => $invoice->id,
            'amount'      => number_format((float) $invoice->total, 2),
            'invoice_url' => route('client.invoices.show', $invoice->id),
        ]));

        AuditLogger::log('invoice.paid', $invoice, ['amount' => $invoice->total]);
        WorkflowEngine::fire('invoice.paid', $invoice);

        return back()->with('success', 'Invoice marked as paid.');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        $invoice->update(['status' => 'cancelled']);

        return back()->with('success', 'Invoice cancelled.');
    }
}
