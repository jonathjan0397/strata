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
use App\Services\DomainRegistrarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'product'      => $product,
            'billingCycle' => $request->billing_cycle,
            'domain'       => $request->domain ?? '',
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
        ]);

        $product = Product::findOrFail($request->product_id);
        abort_if($product->hidden, 404);

        // Resolve promo code if provided
        $promo    = null;
        $discount = 0;

        if ($request->filled('promo_code')) {
            $promo = PromoCode::where('code', strtoupper($request->promo_code))->first();

            if ($promo && $promo->isValid()) {
                // Product restriction
                if ($promo->product_id === null || (int) $promo->product_id === (int) $product->id) {
                    $subtotal = (float) $product->price + (float) $product->setup_fee;
                    $discount = $promo->calculateDiscount($subtotal);
                }
            }
        }

        try {
            DB::transaction(function () use ($request, $product, $promo, $discount) {
                $user     = $request->user();
                $price    = (float) $product->price;
                $setupFee = (float) $product->setup_fee;
                $subtotal = $price + $setupFee;
                $total    = max(0, $subtotal - $discount);

                // 1. Create order
                $order = Order::create([
                    'user_id'    => $user->id,
                    'status'     => 'pending',
                    'subtotal'   => $subtotal,
                    'discount'   => $discount,
                    'total'      => $total,
                    'promo_code' => $promo?->code,
                ]);

                // 2. Order item
                $order->items()->create([
                    'product_id'    => $product->id,
                    'domain'        => $request->domain,
                    'description'   => $product->name.($request->domain ? ' — '.$request->domain : ''),
                    'price'         => $price,
                    'setup_fee'     => $setupFee,
                    'billing_cycle' => $request->billing_cycle,
                ]);

                // 3. Service record (pending provisioning)
                $service = Service::create([
                    'user_id'           => $user->id,
                    'product_id'        => $product->id,
                    'domain'            => $request->domain,
                    'status'            => 'pending',
                    'amount'            => $price,
                    'billing_cycle'     => $request->billing_cycle,
                    'registration_date' => now(),
                    'next_due_date'     => $this->nextDueDate($request->billing_cycle),
                ]);

                // 4. Invoice
                $invoice = Invoice::create([
                    'user_id'    => $user->id,
                    'status'     => 'unpaid',
                    'subtotal'   => $subtotal,
                    'total'      => $total,
                    'amount_due' => $total,
                    'date'       => now(),
                    'due_date'   => now()->addDays((int) Setting::get('invoice_due_days', 7)),
                ]);

                // 5. Invoice line items
                if ($setupFee > 0) {
                    $invoice->items()->create([
                        'service_id'  => $service->id,
                        'description' => 'Setup Fee — '.$product->name,
                        'quantity'    => 1,
                        'unit_price'  => $setupFee,
                        'total'       => $setupFee,
                    ]);
                }

                $invoice->items()->create([
                    'service_id'  => $service->id,
                    'description' => $product->name.($request->domain ? ' — '.$request->domain : ''),
                    'quantity'    => 1,
                    'unit_price'  => $price,
                    'total'       => $price,
                ]);

                // Promo discount line item
                if ($discount > 0 && $promo) {
                    $invoice->items()->create([
                        'service_id'  => $service->id,
                        'description' => 'Promo: '.$promo->code,
                        'quantity'    => 1,
                        'unit_price'  => -$discount,
                        'total'       => -$discount,
                    ]);
                    $promo->increment('uses_count');
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

                // 7. Mark order active
                $order->update(['status' => 'active']);

                session(['last_order_invoice_id' => $invoice->id]);
                session(['last_order_invoice_amount' => $total]);
                session(['last_order_invoice_due' => now()->addDays((int) Setting::get('invoice_due_days', 7))->format('M d, Y')]);
            });

        } catch (Throwable $e) {
            return back()->with('error', 'Order could not be placed: '.$e->getMessage());
        }

        $invoiceId  = session()->pull('last_order_invoice_id');
        $amount     = session()->pull('last_order_invoice_amount');
        $due        = session()->pull('last_order_invoice_due');

        Mail::to($request->user()->email)->queue(new TemplateMailable('invoice.created', [
            'name'        => $request->user()->name,
            'app_name'    => config('app.name'),
            'invoice_id'  => $invoiceId,
            'amount'      => number_format((float) $amount, 2),
            'due_date'    => $due,
            'invoice_url' => route('client.invoices.show', $invoiceId),
        ]));

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
