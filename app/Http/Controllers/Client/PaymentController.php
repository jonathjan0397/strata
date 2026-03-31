<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;
use Throwable;

class PaymentController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe Checkout Session and return the URL.
     */
    public function checkout(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_if($invoice->status === 'paid', 422, 'Invoice is already paid.');
        abort_if((float) $invoice->amount_due <= 0, 422, 'Nothing due on this invoice.');

        try {
            $session = StripeSession::create([
                'mode' => 'payment',
                'currency' => config('services.stripe.currency', 'usd'),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => config('services.stripe.currency', 'usd'),
                        'unit_amount' => (int) round((float) $invoice->amount_due * 100),
                        'product_data' => [
                            'name' => "Invoice #{$invoice->id}",
                            'description' => config('app.name').' — Invoice #'.$invoice->id,
                        ],
                    ],
                ]],
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'user_id' => $request->user()->id,
                ],
                'success_url' => route('client.invoices.show', $invoice->id).'?paid=1',
                'cancel_url' => route('client.invoices.show', $invoice->id),
            ]);

            // Create a pending Payment record so we can reconcile the webhook
            Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
                'gateway' => 'stripe',
                'transaction_id' => $session->id,
                'amount' => $invoice->amount_due,
                'currency' => strtoupper(config('services.stripe.currency', 'usd')),
                'status' => 'pending',
            ]);

            return response()->json(['url' => $session->url]);

        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
