<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Mail\TemplateMailable;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OrderProvisioner;
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

        return Inertia::render('Admin/Invoices/Show', [
            'invoice'  => $invoice,
            'currency' => Setting::get('currency_symbol', '$'),
            'flash'    => session()->only(['success', 'error']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Invoices/Create', [
            'clients'   => User::role('client')->orderBy('name')->get(['id', 'name', 'email', 'country', 'state', 'tax_exempt', 'credit_balance']),
            'taxRates'  => \App\Models\TaxRate::where('active', true)->orderBy('name')->get(['id', 'name', 'rate', 'country', 'state', 'is_default']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id'    => ['required', 'exists:users,id'],
            'date'       => ['nullable', 'date'],
            'due_date'   => ['required', 'date'],
            'status'     => ['nullable', 'in:draft,unpaid'],
            'notes'      => ['nullable', 'string', 'max:5000'],
            'tax_rate'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items'      => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
        ]);

        $subtotal = collect($request->items)
            ->sum(fn ($i) => $i['quantity'] * $i['unit_price']);

        $taxRate    = (float) ($request->tax_rate ?? 0);
        $tax        = round($subtotal * ($taxRate / 100), 2);
        $total      = $subtotal + $tax;

        $invoice = Invoice::create([
            'user_id'    => $request->user_id,
            'status'     => $request->status ?? 'unpaid',
            'subtotal'   => $subtotal,
            'tax_rate'   => $taxRate,
            'tax'        => $tax,
            'total'      => $total,
            'amount_due' => $total,
            'date'       => $request->date ?? now()->toDateString(),
            'due_date'   => $request->due_date,
            'notes'      => $request->notes,
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

    public function download(Invoice $invoice): HttpResponse|\Illuminate\Http\JsonResponse
    {
        $invoice->load(['user', 'items', 'payments']);

        $settings = [
            'company_name'    => \App\Models\Setting::get('company_name', config('app.name')),
            'company_address' => \App\Models\Setting::get('company_address', ''),
            'currency_symbol' => \App\Models\Setting::get('currency_symbol', '$'),
            'logo_path'       => \App\Models\Setting::get('logo_path'),
        ];

        try {
            $pdf = Pdf::loadView('pdf.invoice', compact('invoice', 'settings'))
                ->setPaper('a4', 'portrait');

            return $pdf->download("invoice-{$invoice->id}.pdf");
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    }

    public function markPaid(Invoice $invoice): RedirectResponse
    {
        $invoice->load('user');

        $invoice->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        // Trigger on_payment provisioning for any pending services linked to this invoice
        try {
            OrderProvisioner::handleInvoicePaid($invoice);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("on_payment provisioning failed for invoice #{$invoice->id}: " . $e->getMessage());
        }

        try {
            Mail::to($invoice->user->email)->send(new TemplateMailable('invoice.paid', [
                'name'        => $invoice->user->name,
                'app_name'    => config('app.name'),
                'invoice_id'  => $invoice->id,
                'amount'      => number_format((float) $invoice->total, 2),
                'invoice_url' => route('client.invoices.show', $invoice->id),
            ]));
        } catch (\Throwable) {
            // Mail failure should not prevent the invoice from being marked paid
        }

        AuditLogger::log('invoice.paid', $invoice, ['amount' => $invoice->total]);
        WorkflowEngine::fire('invoice.paid', $invoice);

        return back()->with('success', 'Invoice marked as paid.');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        $invoice->update(['status' => 'cancelled']);

        return back()->with('success', 'Invoice cancelled.');
    }

    public function sendEmail(Invoice $invoice): RedirectResponse
    {
        $invoice->load('user');

        try {
            Mail::to($invoice->user->email)->send(new InvoiceMail($invoice));
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send email: ' . $e->getMessage());
        }

        AuditLogger::log('invoice.emailed', $invoice, ['to' => $invoice->user->email]);

        return back()->with('success', "Invoice #{$invoice->id} emailed to {$invoice->user->email}.");
    }
}
