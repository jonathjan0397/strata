<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientCredit;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\OrderProvisioner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = $request->user()->invoices()->latest();

        if ($status === 'unpaid') {
            $query->where('status', 'unpaid')->where('due_date', '>=', now()->toDateString());
        } elseif ($status === 'overdue') {
            $query->where('status', 'unpaid')->where('due_date', '<', now()->toDateString());
        } elseif ($status === 'paid') {
            $query->where('status', 'paid');
        }

        if ($from) {
            $query->where('date', '>=', $from);
        }
        if ($to) {
            $query->where('date', '<=', $to);
        }

        // Summary totals for the current filtered set (clone before paginating)
        $summary = (clone $query)->reorder()->selectRaw('
            SUM(total) as total_billed,
            SUM(CASE WHEN status = \'paid\' THEN total ELSE 0 END) as total_paid,
            SUM(CASE WHEN status != \'paid\' THEN amount_due ELSE 0 END) as total_outstanding
        ')->first();

        return Inertia::render('Client/Invoices/Index', [
            'invoices' => $query->paginate(20)->withQueryString(),
            'activeFilter' => $status ?? 'all',
            'from' => $from ?? '',
            'to' => $to ?? '',
            'summary' => [
                'total_billed' => (float) ($summary->total_billed ?? 0),
                'total_paid' => (float) ($summary->total_paid ?? 0),
                'total_outstanding' => (float) ($summary->total_outstanding ?? 0),
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->load(['items.service', 'payments', 'creditNotes']);

        $authNetLoginId = config('services.authorizenet.login_id');
        $authNetClientKey = config('services.authorizenet.client_key');

        $bankInstructions = Setting::get('bank_transfer_instructions');

        return Inertia::render('Client/Invoices/Show', [
            'invoice' => $invoice,
            'creditBalance' => (float) $request->user()->credit_balance,
            'hasStripe' => (bool) config('services.stripe.secret'),
            'hasPayPal' => (bool) config('services.paypal.client_id'),
            'authNet' => ($authNetLoginId && $authNetClientKey) ? [
                'loginId' => $authNetLoginId,
                'clientKey' => $authNetClientKey,
                'sandbox' => (bool) config('services.authorizenet.sandbox', true),
            ] : null,
            'bankTransferInstructions' => $bankInstructions ?: null,
        ]);
    }

    /** Apply the client's account credit to reduce an unpaid invoice. */
    public function applyCredit(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_if($invoice->status === 'paid', 422, 'Invoice is already paid.');

        $user = $request->user();
        $available = (float) $user->credit_balance;
        $amountDue = (float) $invoice->amount_due;

        if ($available <= 0) {
            return back()->with('error', 'You have no credit balance available.');
        }

        // Apply as much as we have, up to the full amount due
        $apply = min($available, $amountDue);
        $newAmountDue = round($amountDue - $apply, 2);

        DB::transaction(function () use ($user, $invoice, $apply) {
            ClientCredit::create([
                'user_id' => $user->id,
                'amount' => -$apply,  // negative = debit
                'description' => "Applied to Invoice #{$invoice->id}",
                'invoice_id' => $invoice->id,
            ]);

            $user->decrement('credit_balance', $apply);

            $newAmountDue = round((float) $invoice->amount_due - $apply, 2);
            $newCreditApplied = round((float) $invoice->credit_applied + $apply, 2);

            $invoice->update([
                'credit_applied' => $newCreditApplied,
                'amount_due' => $newAmountDue,
                'status' => $newAmountDue <= 0 ? 'paid' : $invoice->status,
                'paid_at' => $newAmountDue <= 0 ? now() : $invoice->paid_at,
            ]);
        });

        // If credit fully covered the invoice, trigger on_payment provisioning
        if ($newAmountDue <= 0) {
            $invoice->refresh();
            try {
                OrderProvisioner::handleInvoicePaid($invoice);
            } catch (\Throwable $e) {
                Log::error("on_payment provisioning failed for invoice #{$invoice->id}: ".$e->getMessage());
            }
        }

        return back()->with('success', '$'.number_format($apply, 2).' credit applied to invoice.');
    }

    public function download(Request $request, Invoice $invoice): HttpResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->load(['user', 'items', 'payments']);

        $settings = [
            'company_name' => Setting::get('company_name', config('app.name')),
            'company_address' => Setting::get('company_address', ''),
            'currency_symbol' => Setting::get('currency_symbol', '$'),
            'logo_path' => Setting::get('logo_path'),
        ];

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice', 'settings'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("invoice-{$invoice->id}.pdf");
    }
}
