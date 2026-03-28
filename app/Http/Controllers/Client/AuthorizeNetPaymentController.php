<?php

namespace App\Http\Controllers\Client;

use App\Gateways\AuthorizeNetGateway;
use App\Http\Controllers\Controller;
use App\Mail\TemplateMailable;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\OrderProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AuthorizeNetPaymentController extends Controller
{
    public function checkout(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_if($invoice->status === 'paid', 422, 'Invoice is already paid.');
        abort_if((float) $invoice->amount_due <= 0, 422, 'Nothing due on this invoice.');

        $request->validate([
            'opaque_descriptor' => ['required', 'string'],
            'opaque_value'      => ['required', 'string'],
        ]);

        try {
            $gateway = new AuthorizeNetGateway();

            $result = $gateway->charge((float) $invoice->amount_due, 'usd', [
                'opaque_descriptor' => $request->opaque_descriptor,
                'opaque_value'      => $request->opaque_value,
            ]);

            Payment::create([
                'invoice_id'     => $invoice->id,
                'user_id'        => $request->user()->id,
                'gateway'        => 'authorizenet',
                'transaction_id' => $result['id'],
                'amount'         => $invoice->amount_due,
                'currency'       => 'USD',
                'status'         => 'completed',
                'paid_at'        => now(),
            ]);

            $invoice->update([
                'status'     => 'paid',
                'paid_at'    => now(),
                'amount_due' => 0,
            ]);

            // Trigger on_payment provisioning
            try {
                OrderProvisioner::handleInvoicePaid($invoice);
            } catch (\Throwable $e) {
                Log::error("on_payment provisioning failed for invoice #{$invoice->id}: " . $e->getMessage());
            }

            try {
                Mail::to($request->user()->email)->send(new TemplateMailable('invoice.paid', [
                    'name'        => $request->user()->name,
                    'app_name'    => config('app.name'),
                    'invoice_id'  => $invoice->id,
                    'amount'      => number_format((float) $invoice->total, 2),
                    'invoice_url' => route('client.invoices.show', $invoice->id),
                ]));
            } catch (\Throwable) {
                // mail failure must not block payment confirmation
            }

            return response()->json(['success' => true]);

        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
