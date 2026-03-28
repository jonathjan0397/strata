<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientCredit;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $query = $request->user()->invoices()->latest();

        if ($status === 'unpaid') {
            $query->where('status', 'unpaid')->where('due_date', '>=', now()->toDateString());
        } elseif ($status === 'overdue') {
            $query->where('status', 'unpaid')->where('due_date', '<', now()->toDateString());
        } elseif ($status === 'paid') {
            $query->where('status', 'paid');
        }

        return Inertia::render('Client/Invoices/Index', [
            'invoices'     => $query->paginate(20)->withQueryString(),
            'activeFilter' => $status ?? 'all',
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->load(['items.service', 'payments']);

        $authNetLoginId  = config('services.authorizenet.login_id');
        $authNetClientKey = config('services.authorizenet.client_key');

        return Inertia::render('Client/Invoices/Show', [
            'invoice'       => $invoice,
            'creditBalance' => (float) $request->user()->credit_balance,
            'hasStripe'     => (bool) config('services.stripe.secret'),
            'hasPayPal'     => (bool) config('services.paypal.client_id'),
            'authNet'       => ($authNetLoginId && $authNetClientKey) ? [
                'loginId'   => $authNetLoginId,
                'clientKey' => $authNetClientKey,
                'sandbox'   => (bool) config('services.authorizenet.sandbox', true),
            ] : null,
        ]);
    }

    /** Apply the client's account credit to reduce an unpaid invoice. */
    public function applyCredit(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_if($invoice->status === 'paid', 422, 'Invoice is already paid.');

        $user          = $request->user();
        $available     = (float) $user->credit_balance;
        $amountDue     = (float) $invoice->amount_due;

        if ($available <= 0) {
            return back()->with('error', 'You have no credit balance available.');
        }

        // Apply as much as we have, up to the full amount due
        $apply = min($available, $amountDue);

        DB::transaction(function () use ($user, $invoice, $apply) {
            ClientCredit::create([
                'user_id'     => $user->id,
                'amount'      => -$apply,  // negative = debit
                'description' => "Applied to Invoice #{$invoice->id}",
                'invoice_id'  => $invoice->id,
            ]);

            $user->decrement('credit_balance', $apply);

            $newAmountDue     = round((float) $invoice->amount_due - $apply, 2);
            $newCreditApplied = round((float) $invoice->credit_applied + $apply, 2);

            $invoice->update([
                'credit_applied' => $newCreditApplied,
                'amount_due'     => $newAmountDue,
                'status'         => $newAmountDue <= 0 ? 'paid' : $invoice->status,
                'paid_at'        => $newAmountDue <= 0 ? now() : $invoice->paid_at,
            ]);
        });

        return back()->with('success', '$'.number_format($apply, 2).' credit applied to invoice.');
    }

    public function download(Request $request, Invoice $invoice): HttpResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->load(['user', 'items', 'payments']);

        $settings = [
            'company_name'    => \App\Models\Setting::get('company_name', config('app.name')),
            'company_address' => \App\Models\Setting::get('company_address', ''),
            'currency_symbol' => \App\Models\Setting::get('currency_symbol', '$'),
            'logo_path'       => \App\Models\Setting::get('logo_path'),
        ];

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice', 'settings'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("invoice-{$invoice->id}.pdf");
    }
}
