<?php

namespace App\Http\Controllers;

use App\Mail\TemplateMailable;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\OrderProvisioner;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        if ($secret) {
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $secret);
            } catch (SignatureVerificationException $e) {
                return response('Invalid signature.', 400);
            }
        } else {
            // No webhook secret configured — parse without verification
            try {
                $event = Event::constructFrom(json_decode($payload, true));
            } catch (\Throwable $e) {
                return response('Invalid payload.', 400);
            }
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'checkout.session.expired'   => $this->handleCheckoutExpired($event),
            default                      => null,
        };

        return response('', 200);
    }

    private function handleCheckoutCompleted(Event $event): void
    {
        $session   = $event->data->object;
        $invoiceId = $session->metadata->invoice_id ?? null;

        if (! $invoiceId) {
            return;
        }

        $invoice = Invoice::find($invoiceId);

        if (! $invoice || $invoice->status === 'paid') {
            return;
        }

        // Update pending Payment record
        $payment = Payment::where('transaction_id', $session->id)->first();

        if ($payment) {
            $payment->update([
                'status'           => 'completed',
                'gateway_response' => (array) $session,
                'paid_at'          => now(),
            ]);
        } else {
            // Fallback: create payment record if it was somehow missed
            Payment::create([
                'invoice_id'     => $invoice->id,
                'user_id'        => $invoice->user_id,
                'gateway'        => 'stripe',
                'transaction_id' => $session->id,
                'amount'         => $invoice->amount_due,
                'currency'       => strtoupper($session->currency ?? 'usd'),
                'status'         => 'completed',
                'gateway_response' => (array) $session,
                'paid_at'        => now(),
            ]);
        }

        // Mark invoice paid
        $invoice->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        $invoice->load('user');

        // Trigger on_payment provisioning for any pending services linked to this invoice
        try {
            OrderProvisioner::handleInvoicePaid($invoice);
        } catch (\Throwable $e) {
            Log::error("on_payment provisioning failed for invoice #{$invoice->id}: " . $e->getMessage());
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
            // mail failure must not block invoice being marked paid
        }
    }

    private function handleCheckoutExpired(Event $event): void
    {
        $session = $event->data->object;

        Payment::where('transaction_id', $session->id)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);
    }
}
