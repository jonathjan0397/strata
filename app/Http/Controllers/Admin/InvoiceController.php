<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Mail\TemplateMailable;
use App\Models\ClientCredit;
use App\Models\CreditNote;
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
use Illuminate\Support\Facades\DB;
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
        $invoice->load(['user', 'items.service.product', 'payments', 'creditNotes']);

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

    public function issueCreditNote(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01', 'max:' . $invoice->total],
            'reason'      => ['required', 'string', 'max:500'],
            'disposition' => ['required', 'in:balance,invoice'],
            'notes'       => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $invoice) {
            $cn = CreditNote::create([
                'invoice_id'  => $invoice->id,
                'user_id'     => $invoice->user_id,
                'amount'      => $request->amount,
                'reason'      => $request->reason,
                'disposition' => $request->disposition,
                'notes'       => $request->notes,
                'status'      => 'issued',
                'issued_at'   => now(),
            ]);

            // Generate number: CN-YYYYMMDD-NNNN
            $cn->update([
                'credit_note_number' => 'CN-' . now()->format('Ymd') . '-' . str_pad($cn->id, 4, '0', STR_PAD_LEFT),
            ]);

            $amount = (float) $request->amount;

            if ($request->disposition === 'balance') {
                // Credit the client's account balance
                ClientCredit::create([
                    'user_id'     => $invoice->user_id,
                    'amount'      => $amount,
                    'description' => "Credit note {$cn->credit_note_number} — {$cn->reason}",
                    'invoice_id'  => $invoice->id,
                ]);

                $invoice->user->increment('credit_balance', $amount);
                $cn->update(['status' => 'applied']);

            } else {
                // Apply directly to the invoice amount_due
                $newAmountDue = max(0, round((float) $invoice->amount_due - $amount, 2));

                $invoice->update([
                    'amount_due'     => $newAmountDue,
                    'credit_applied' => round((float) $invoice->credit_applied + $amount, 2),
                    'status'         => $newAmountDue <= 0 ? 'paid' : $invoice->status,
                    'paid_at'        => $newAmountDue <= 0 ? now() : $invoice->paid_at,
                ]);

                $cn->update(['status' => 'applied']);
            }

            AuditLogger::log('invoice.credit_note_issued', $invoice, [
                'credit_note'  => $cn->credit_note_number,
                'amount'       => $amount,
                'disposition'  => $request->disposition,
            ]);
        });

        return back()->with('success', 'Credit note issued.');
    }

    public function voidCreditNote(Invoice $invoice, CreditNote $creditNote): RedirectResponse
    {
        abort_unless($creditNote->invoice_id === $invoice->id, 404);
        abort_unless($creditNote->status !== 'voided', 422);

        DB::transaction(function () use ($invoice, $creditNote) {
            // Reverse balance credit if that was the disposition
            if ($creditNote->disposition === 'balance') {
                $amount = (float) $creditNote->amount;

                ClientCredit::create([
                    'user_id'     => $creditNote->user_id,
                    'amount'      => -$amount,
                    'description' => "Void of credit note {$creditNote->credit_note_number}",
                    'invoice_id'  => $invoice->id,
                ]);

                $creditNote->user->decrement('credit_balance', $amount);
            }

            // Reverse invoice application
            if ($creditNote->disposition === 'invoice') {
                $amount = (float) $creditNote->amount;

                $invoice->update([
                    'amount_due'     => round((float) $invoice->amount_due + $amount, 2),
                    'credit_applied' => max(0, round((float) $invoice->credit_applied - $amount, 2)),
                    'status'         => $invoice->status === 'paid' && $invoice->amount_due > 0 ? 'unpaid' : $invoice->status,
                    'paid_at'        => $invoice->status === 'paid' && $invoice->amount_due > 0 ? null : $invoice->paid_at,
                ]);
            }

            $creditNote->update(['status' => 'voided']);

            AuditLogger::log('invoice.credit_note_voided', $invoice, [
                'credit_note' => $creditNote->credit_note_number,
            ]);
        });

        return back()->with('success', 'Credit note voided.');
    }
}
