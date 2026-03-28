<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\OrderProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Throwable;

class PayPalPaymentController extends Controller
{
    private function client(): PayPalClient
    {
        $provider = new PayPalClient([
            'mode'    => config('services.paypal.mode', 'sandbox'),
            'sandbox' => [
                'client_id'     => config('services.paypal.client_id'),
                'client_secret' => config('services.paypal.client_secret'),
                'app_id'        => '',
            ],
            'live' => [
                'client_id'     => config('services.paypal.client_id'),
                'client_secret' => config('services.paypal.client_secret'),
                'app_id'        => '',
            ],
            'payment_action' => 'Sale',
            'currency'       => config('services.paypal.currency', 'USD'),
            'notify_url'     => '',
            'locale'         => 'en_US',
            'validate_ssl'   => true,
        ]);

        $provider->getAccessToken();

        return $provider;
    }

    /**
     * Create a PayPal order and return the approval URL.
     */
    public function checkout(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_if($invoice->status === 'paid', 422, 'Invoice is already paid.');
        abort_if((float) $invoice->amount_due <= 0, 422, 'Nothing due on this invoice.');

        try {
            $provider = $this->client();

            $currency = strtoupper(config('services.paypal.currency', 'USD'));
            $amount   = number_format((float) $invoice->amount_due, 2, '.', '');

            $order = $provider->createOrder([
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $invoice->id,
                    'description'  => config('app.name').' — Invoice #'.$invoice->id,
                    'amount'       => [
                        'currency_code' => $currency,
                        'value'         => $amount,
                    ],
                ]],
                'application_context' => [
                    'return_url' => route('client.invoices.paypal.return', $invoice->id),
                    'cancel_url' => route('client.invoices.paypal.cancel', $invoice->id),
                    'brand_name' => config('app.name'),
                    'user_action' => 'PAY_NOW',
                ],
            ]);

            if (empty($order['id'])) {
                return response()->json(['error' => 'PayPal order creation failed.'], 500);
            }

            // Store a pending payment record tied to the PayPal order ID
            Payment::create([
                'invoice_id'     => $invoice->id,
                'user_id'        => $request->user()->id,
                'gateway'        => 'paypal',
                'transaction_id' => $order['id'],
                'amount'         => $invoice->amount_due,
                'currency'       => $currency,
                'status'         => 'pending',
            ]);

            // Find the approval link
            $approvalUrl = collect($order['links'] ?? [])
                ->firstWhere('rel', 'approve')['href'] ?? null;

            if (! $approvalUrl) {
                return response()->json(['error' => 'No approval URL returned by PayPal.'], 500);
            }

            return response()->json(['url' => $approvalUrl]);

        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PayPal redirects here after buyer approves — capture the payment.
     */
    public function return(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $orderId = $request->query('token'); // PayPal sends the order ID as 'token'

        if (! $orderId) {
            return redirect()->route('client.invoices.show', $invoice->id)
                ->with('error', 'Payment could not be verified.');
        }

        try {
            $provider = $this->client();
            $capture  = $provider->capturePaymentOrder($orderId);

            $status = $capture['status'] ?? null;

            if ($status === 'COMPLETED') {
                $captureId = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? $orderId;

                // Update pending Payment record
                Payment::where('transaction_id', $orderId)
                    ->where('status', 'pending')
                    ->update([
                        'status'           => 'completed',
                        'transaction_id'   => $captureId,
                        'gateway_response' => json_encode($capture),
                        'paid_at'          => now(),
                    ]);

                // Mark invoice paid
                if ($invoice->status !== 'paid') {
                    $invoice->update([
                        'status'  => 'paid',
                        'paid_at' => now(),
                    ]);

                    // Trigger on_payment provisioning
                    try {
                        OrderProvisioner::handleInvoicePaid($invoice);
                    } catch (\Throwable $e) {
                        Log::error("on_payment provisioning failed for invoice #{$invoice->id}: " . $e->getMessage());
                    }
                }

                return redirect()->route('client.invoices.show', $invoice->id)
                    ->with('success', 'Payment received via PayPal.');
            }

            return redirect()->route('client.invoices.show', $invoice->id)
                ->with('error', 'PayPal payment could not be completed (status: '.($status ?? 'unknown').').');

        } catch (Throwable $e) {
            return redirect()->route('client.invoices.show', $invoice->id)
                ->with('error', 'PayPal error: '.$e->getMessage());
        }
    }

    /**
     * PayPal redirects here if the buyer cancels.
     */
    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $orderId = $request->query('token');

        if ($orderId) {
            Payment::where('transaction_id', $orderId)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);
        }

        return redirect()->route('client.invoices.show', $invoice->id)
            ->with('error', 'PayPal payment was cancelled.');
    }
}
