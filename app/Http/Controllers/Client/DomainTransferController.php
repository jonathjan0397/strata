<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\TemplateMailable;
use App\Models\ClientCredit;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TldPrice;
use App\Services\AuditLogger;
use App\Services\OrderProvisioner;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DomainTransferController extends Controller
{
    /** Transfer initiation page. */
    public function search(): Response
    {
        return Inertia::render('Client/DomainTransfer/Search');
    }

    /** Checkout page — validates domain + auth code, resolves transfer pricing. */
    public function checkout(Request $request): Response|RedirectResponse
    {
        $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'auth_code' => ['required', 'string', 'max:255'],
        ]);

        $domain = strtolower(trim($request->input('domain')));
        $authCode = $request->input('auth_code');

        $tldPrice = TldPrice::forDomain($domain);

        if (! $tldPrice || $tldPrice->transfer_price === null) {
            return redirect()->route('client.domain-transfer.search')
                ->with('flash', ['error' => 'Transfer pricing is not available for this domain. Please contact support.']);
        }

        $user = $request->user();

        return Inertia::render('Client/DomainTransfer/Checkout', [
            'domain' => $domain,
            'authCode' => $authCode,
            'price' => $tldPrice->transfer_price,
            'currency' => $tldPrice->currency,
            'creditBalance' => (float) $user->credit_balance,
            'prefill' => [
                'first' => explode(' ', $user->name)[0] ?? '',
                'last' => implode(' ', array_slice(explode(' ', $user->name), 1)) ?: '',
                'email' => $user->email,
            ],
        ]);
    }

    /** Place the domain transfer order. */
    public function place(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'auth_code' => ['required', 'string', 'max:255'],
            'apply_credit' => ['nullable', 'boolean'],
            'registrant_first' => ['required', 'string', 'max:100'],
            'registrant_last' => ['required', 'string', 'max:100'],
            'registrant_email' => ['required', 'email', 'max:255'],
            'registrant_phone' => ['required', 'string', 'max:30'],
            'registrant_address' => ['required', 'string', 'max:255'],
            'registrant_city' => ['required', 'string', 'max:100'],
            'registrant_state' => ['required', 'string', 'max:100'],
            'registrant_zip' => ['required', 'string', 'max:20'],
            'registrant_country' => ['required', 'string', 'size:2'],
        ]);

        $domain = strtolower(trim($data['domain']));
        $authCode = $data['auth_code'];
        $tldPrice = TldPrice::forDomain($domain);
        $applyCredit = (bool) $request->input('apply_credit', false);

        if (! $tldPrice || $tldPrice->transfer_price === null) {
            return back()->with('flash', ['error' => 'Transfer pricing is not available for this domain.']);
        }

        $total = $tldPrice->transfer_price;
        $invoiceId = null;

        try {
            DB::transaction(function () use ($request, $domain, $authCode, $total, $applyCredit, $data, &$invoiceId) {
                $user = $request->user();

                // 1. Service record (no product — domain transfer)
                $service = Service::create([
                    'user_id' => $user->id,
                    'product_id' => null,
                    'domain' => $domain,
                    'status' => 'pending',
                    'amount' => $total,
                    'billing_cycle' => 'annual',
                    'registration_date' => now(),
                    'next_due_date' => now()->addYear(),
                ]);

                // 2. Domain record (transfer_pending until invoice paid)
                Domain::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'name' => $domain,
                    'registrar' => config('registrars.default', 'namecheap'),
                    'status' => 'transfer_pending',
                    'auto_renew' => true,
                    'registrar_data' => [
                        'auth_code' => $authCode,
                        'registrant_first' => $data['registrant_first'],
                        'registrant_last' => $data['registrant_last'],
                        'registrant_email' => $data['registrant_email'],
                        'registrant_phone' => $data['registrant_phone'],
                        'registrant_address' => $data['registrant_address'],
                        'registrant_city' => $data['registrant_city'],
                        'registrant_state' => $data['registrant_state'],
                        'registrant_zip' => $data['registrant_zip'],
                        'registrant_country' => $data['registrant_country'],
                    ],
                ]);

                // 3. Invoice
                $dueDate = now()->addDays((int) Setting::get('invoice_due_days', 7));

                $invoice = Invoice::create([
                    'user_id' => $user->id,
                    'status' => 'unpaid',
                    'subtotal' => $total,
                    'tax_rate' => 0,
                    'tax' => 0,
                    'total' => $total,
                    'amount_due' => $total,
                    'date' => now(),
                    'due_date' => $dueDate,
                ]);

                $invoice->items()->create([
                    'service_id' => $service->id,
                    'description' => "Domain Transfer — {$domain}",
                    'quantity' => 1,
                    'unit_price' => $total,
                    'total' => $total,
                ]);

                // 4. Apply account credit if requested
                if ($applyCredit && (float) $user->credit_balance > 0) {
                    $available = (float) $user->credit_balance;
                    $apply = min($available, (float) $invoice->amount_due);
                    $newAmountDue = round((float) $invoice->amount_due - $apply, 2);

                    ClientCredit::create([
                        'user_id' => $user->id,
                        'amount' => -$apply,
                        'description' => "Applied at checkout — Invoice #{$invoice->id}",
                        'invoice_id' => $invoice->id,
                    ]);

                    $user->decrement('credit_balance', $apply);

                    $invoice->update([
                        'credit_applied' => $apply,
                        'amount_due' => $newAmountDue,
                        'status' => $newAmountDue <= 0 ? 'paid' : 'unpaid',
                        'paid_at' => $newAmountDue <= 0 ? now() : null,
                    ]);
                }

                $invoiceId = $invoice->id;
            });

        } catch (Throwable $e) {
            Log::error("Domain transfer order failed for {$domain}: ".$e->getMessage());

            return back()->with('flash', ['error' => 'Order could not be placed: '.$e->getMessage()]);
        }

        // If fully covered by credit, trigger transfer initiation now
        $invoice = Invoice::find($invoiceId);
        if ($invoice && $invoice->isPaid()) {
            try {
                OrderProvisioner::handleInvoicePaid($invoice);
                AuditLogger::log('invoice.paid', $invoice, ['amount' => $invoice->total]);
                WorkflowEngine::fire('invoice.paid', $invoice);
            } catch (Throwable $e) {
                Log::error("Post-order domain provisioning failed for invoice #{$invoiceId}: ".$e->getMessage());
            }
        }

        try {
            Mail::to($request->user()->email)->send(new TemplateMailable('invoice.created', [
                'name' => $request->user()->name,
                'app_name' => config('app.name'),
                'invoice_id' => $invoiceId,
                'amount' => number_format($total, 2),
                'due_date' => now()->addDays((int) Setting::get('invoice_due_days', 7))->format('M d, Y'),
                'invoice_url' => route('client.invoices.show', $invoiceId),
            ]));
        } catch (Throwable $e) {
            Log::warning("Domain transfer order confirmation email failed for invoice #{$invoiceId}: ".$e->getMessage());
        }

        return redirect()->route('client.invoices.show', $invoiceId)
            ->with('success', 'Domain transfer order placed! Pay your invoice to initiate the transfer.');
    }
}
