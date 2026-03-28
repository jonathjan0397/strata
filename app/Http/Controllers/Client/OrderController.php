<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\TemplateMailable;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Services\DomainRegistrarService;
use App\Services\OrderProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OrderController extends Controller
{
    /** Product catalog */
    public function catalog(): Response
    {
        $products = Product::where('hidden', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Client/Order/Catalog', [
            'products' => $products,
        ]);
    }

    /** Checkout page — receives product_id + billing_cycle + optional domain */
    public function checkout(Request $request): Response
    {
        $request->validate([
            'product_id'    => ['required', 'exists:products,id'],
            'billing_cycle' => ['required', 'string'],
            'domain'        => ['nullable', 'string', 'max:253'],
        ]);

        $product = Product::findOrFail($request->product_id);

        abort_if($product->hidden, 404);

        return Inertia::render('Client/Order/Checkout', [
            'product'       => $product,
            'billingCycle'  => $request->billing_cycle,
            'domain'        => $request->domain ?? '',
            'creditBalance' => (float) $request->user()->credit_balance,
        ]);
    }

    /** Place the order — creates Order, OrderItem, Service, Invoice */
    public function place(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id'    => ['required', 'exists:products,id'],
            'billing_cycle' => ['required', 'string'],
            'domain'        => ['nullable', 'string', 'max:253'],
            'promo_code'    => ['nullable', 'string', 'max:64'],
            'apply_credit'  => ['nullable', 'boolean'],
            'client_notes'  => ['nullable', 'string', 'max:2000'],
        ]);

        $product = Product::findOrFail($request->product_id);
        abort_if($product->hidden, 404);

        // Resolve promo code if provided
        $promo    = null;
        $discount = 0;

        if ($request->filled('promo_code')) {
            $promo = PromoCode::where('code', strtoupper($request->promo_code))->first();

            if ($promo && $promo->isValid($request->user())) {
                // Product restriction
                if ($promo->product_id === null || (int) $promo->product_id === (int) $product->id) {
                    $discount = $promo->calculateDiscount(
                        (float) $product->price,
                        (float) $product->setup_fee
                    );
                }
            }
        }

        $applyCredit = (bool) $request->input('apply_credit', false);

        // These will be populated inside the transaction
        $createdService = null;
        $createdOrder   = null;

        try {
            DB::transaction(function () use ($request, $product, $promo, $discount, $applyCredit, &$createdService, &$createdOrder) {
                $user     = $request->user()->load('group');
                $price    = (float) $product->price;
                $setupFee = (float) $product->setup_fee;
                $subtotal = $price + $setupFee;

                // Apply client group discount on top of any promo discount
                $groupDiscount = 0;
                if ($user->group && $discount === 0) {
                    $groupDiscount = $user->group->calculateDiscount($subtotal);
                }

                $discountedSubtotal = max(0, $subtotal - $discount - $groupDiscount);

                // Resolve tax
                $taxRate    = $product->taxable ? TaxRate::resolveForUser($user) : null;
                $taxRateVal = $taxRate ? (float) $taxRate->rate : 0;
                $tax        = $taxRate ? round($discountedSubtotal * ($taxRateVal / 100), 2) : 0;
                $total      = $discountedSubtotal + $tax;

                // 1. Create order
                $order = Order::create([
                    'user_id'      => $user->id,
                    'status'       => 'pending',
                    'subtotal'     => $subtotal,
                    'discount'     => $discount + $groupDiscount,
                    'total'        => $total,
                    'promo_code'   => $promo?->code,
                    'client_notes' => $request->client_notes,
                ]);

                // Generate human-readable order number: ORD-YYYYMMDD-NNNN
                $order->update([
                    'order_number' => 'ORD-' . now()->format('Ymd') . '-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                ]);

                // 2. Service record — trial products activate immediately
                $isTrial      = $product->trial_days > 0;
                $trialEndsAt  = $isTrial ? now()->addDays((int) $product->trial_days)->toDateString() : null;
                $nextDue      = $isTrial
                    ? now()->addDays((int) $product->trial_days)
                    : $this->nextDueDate($request->billing_cycle);

                $service = Service::create([
                    'user_id'           => $user->id,
                    'product_id'        => $product->id,
                    'domain'            => $request->domain,
                    'status'            => $isTrial ? 'active' : 'pending',
                    'amount'            => $price,
                    'billing_cycle'     => $request->billing_cycle,
                    'registration_date' => now(),
                    'next_due_date'     => $nextDue,
                    'trial_ends_at'     => $trialEndsAt,
                ]);

                // 3. Order item — linked to the service
                $order->items()->create([
                    'product_id'    => $product->id,
                    'service_id'    => $service->id,
                    'domain'        => $request->domain,
                    'description'   => $product->name . ($request->domain ? ' — ' . $request->domain : ''),
                    'price'         => $price,
                    'setup_fee'     => $setupFee,
                    'billing_cycle' => $request->billing_cycle,
                ]);

                // 4. Invoice
                // Trial orders: invoice is due when the trial expires
                $invoiceDueDate = $isTrial
                    ? now()->addDays((int) $product->trial_days)
                    : now()->addDays((int) Setting::get('invoice_due_days', 7));

                $invoice = Invoice::create([
                    'user_id'    => $user->id,
                    'status'     => 'unpaid',
                    'subtotal'   => $discountedSubtotal,
                    'tax_rate'   => $taxRateVal,
                    'tax'        => $tax,
                    'total'      => $total,
                    'amount_due' => $total,
                    'date'       => now(),
                    'due_date'   => $invoiceDueDate,
                ]);

                // 5. Invoice line items
                if ($setupFee > 0) {
                    $invoice->items()->create([
                        'service_id'  => $service->id,
                        'description' => 'Setup Fee — ' . $product->name,
                        'quantity'    => 1,
                        'unit_price'  => $setupFee,
                        'total'       => $setupFee,
                    ]);
                }

                $invoice->items()->create([
                    'service_id'  => $service->id,
                    'description' => $product->name . ($request->domain ? ' — ' . $request->domain : ''),
                    'quantity'    => 1,
                    'unit_price'  => $price,
                    'total'       => $price,
                ]);

                // Promo discount line item
                if ($discount > 0 && $promo) {
                    $invoice->items()->create([
                        'service_id'  => $service->id,
                        'description' => 'Promo: ' . $promo->code,
                        'quantity'    => 1,
                        'unit_price'  => -$discount,
                        'total'       => -$discount,
                    ]);
                    $promo->increment('uses_count');
                }

                // Tax line item
                if ($tax > 0 && $taxRate) {
                    $taxLabel = Setting::get('tax_label', 'Tax');
                    $invoice->items()->create([
                        'service_id'  => $service->id,
                        'description' => "{$taxLabel} ({$taxRateVal}%)",
                        'quantity'    => 1,
                        'unit_price'  => $tax,
                        'total'       => $tax,
                    ]);
                }

                // Group discount line item
                if ($groupDiscount > 0 && $user->group) {
                    $invoice->items()->create([
                        'service_id'  => $service->id,
                        'description' => 'Group Discount: ' . $user->group->name,
                        'quantity'    => 1,
                        'unit_price'  => -$groupDiscount,
                        'total'       => -$groupDiscount,
                    ]);
                }

                // 6. For domain products, create a Domain record (registration triggered on payment)
                if ($product->type === 'domain' && $request->domain) {
                    Domain::create([
                        'user_id'    => $user->id,
                        'service_id' => $service->id,
                        'name'       => $request->domain,
                        'registrar'  => config('registrars.default', 'namecheap'),
                        'status'     => 'pending',
                        'auto_renew' => true,
                    ]);
                }

                // 7. Apply account credit if requested
                if ($applyCredit && (float) $user->credit_balance > 0) {
                    $available    = (float) $user->credit_balance;
                    $apply        = min($available, (float) $invoice->amount_due);
                    $newAmountDue = round((float) $invoice->amount_due - $apply, 2);

                    \App\Models\ClientCredit::create([
                        'user_id'     => $user->id,
                        'amount'      => -$apply,
                        'description' => "Applied at checkout — Invoice #{$invoice->id}",
                        'invoice_id'  => $invoice->id,
                    ]);

                    $user->decrement('credit_balance', $apply);

                    $invoice->update([
                        'credit_applied' => $apply,
                        'amount_due'     => $newAmountDue,
                        'status'         => $newAmountDue <= 0 ? 'paid' : 'unpaid',
                        'paid_at'        => $newAmountDue <= 0 ? now() : null,
                    ]);
                }

                session(['last_order_invoice_id'     => $invoice->id]);
                session(['last_order_invoice_amount' => $total]);
                session(['last_order_invoice_due'    => $invoiceDueDate->format('M d, Y')]);

                $createdService = $service;
                $createdOrder   = $order;
            });

        } catch (Throwable $e) {
            return back()->with('error', 'Order could not be placed: ' . $e->getMessage());
        }

        $invoiceId = session()->pull('last_order_invoice_id');
        $amount    = session()->pull('last_order_invoice_amount');
        $due       = session()->pull('last_order_invoice_due');

        // Trial products provision immediately (service is already active)
        $triggerProvision = $createdService && (
            $product->autosetup === 'on_order' ||
            ($product->trial_days > 0 && in_array($product->autosetup, ['on_order', 'on_payment', 'manual']))
        );

        if ($triggerProvision) {
            try {
                $createdService->refresh();
                OrderProvisioner::provision($createdService);
            } catch (Throwable $e) {
                Log::error("on_order provisioning failed for service #{$createdService->id}: " . $e->getMessage());
            }
        }

        // If invoice was fully covered by credit, trigger on_payment provisioning too
        if ($createdService && $product->autosetup === 'on_payment') {
            $invoice = Invoice::with('items.service.product')->find($invoiceId);
            if ($invoice && $invoice->status === 'paid') {
                try {
                    OrderProvisioner::handleInvoicePaid($invoice);
                } catch (Throwable $e) {
                    Log::error("on_payment provisioning failed for invoice #{$invoiceId}: " . $e->getMessage());
                }
            }
        }

        try {
            Mail::to($request->user()->email)->send(new TemplateMailable('invoice.created', [
                'name'        => $request->user()->name,
                'app_name'    => config('app.name'),
                'invoice_id'  => $invoiceId,
                'amount'      => number_format((float) $amount, 2),
                'due_date'    => $due,
                'invoice_url' => route('client.invoices.show', $invoiceId),
            ]));
        } catch (\Throwable) {
            // mail failure must not block order confirmation
        }

        return redirect()->route('client.invoices.show', $invoiceId)
            ->with('success', 'Order placed! Pay your invoice below to activate your service.');
    }

    private function nextDueDate(string $cycle): \Carbon\Carbon
    {
        return match ($cycle) {
            'monthly'     => now()->addMonth(),
            'quarterly'   => now()->addMonths(3),
            'semi_annual' => now()->addMonths(6),
            'annual'      => now()->addYear(),
            'biennial'    => now()->addYears(2),
            'triennial'   => now()->addYears(3),
            default       => now()->addMonth(),
        };
    }
}
