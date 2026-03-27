<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);

        $product = Product::findOrFail($request->product_id);
        abort_if($product->hidden, 404);

        try {
            DB::transaction(function () use ($request, $product) {
                $user     = $request->user();
                $price    = (float) $product->price;
                $setupFee = (float) $product->setup_fee;
                $total    = $price + $setupFee;

                // 1. Create order
                $order = Order::create([
                    'user_id'  => $user->id,
                    'status'   => 'pending',
                    'subtotal' => $total,
                    'total'    => $total,
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
                    'subtotal'   => $total,
                    'total'      => $total,
                    'amount_due' => $total,
                    'date'       => now(),
                    'due_date'   => now()->addDays(7),
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

                // 6. Mark order active
                $order->update(['status' => 'active']);

                session(['last_order_invoice_id' => $invoice->id]);
            });

        } catch (Throwable $e) {
            return back()->with('error', 'Order could not be placed: '.$e->getMessage());
        }

        $invoiceId = session()->pull('last_order_invoice_id');

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
