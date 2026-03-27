<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
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

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            return response('Invalid signature.', 400);
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
    }

    private function handleCheckoutExpired(Event $event): void
    {
        $session = $event->data->object;

        Payment::where('transaction_id', $session->id)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);
    }
}
