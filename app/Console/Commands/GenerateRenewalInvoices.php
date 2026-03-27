<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\Setting;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class GenerateRenewalInvoices extends Command
{
    protected $signature   = 'billing:generate-invoices {--days=14 : Generate invoices for services due within N days}';
    protected $description = 'Generate renewal invoices for services approaching their due date';

    public function handle(): int
    {
        $days = $this->option('days') !== '14'
            ? (int) $this->option('days')
            : (int) Setting::get('invoice_due_days', 14);
        $cutoff = now()->addDays($days);

        $services = Service::with(['user', 'product'])
            ->where('status', 'active')
            ->where('next_due_date', '<=', $cutoff)
            ->whereDoesntHave('invoiceItems', function ($q) {
                // Skip services that already have an unpaid invoice for the upcoming cycle
                $q->whereHas('invoice', fn ($i) => $i->where('status', 'unpaid')
                    ->where('due_date', '>=', now()));
            })
            ->get();

        $count = 0;

        foreach ($services as $service) {
            $price    = (float) $service->amount;
            $dueDate  = $service->next_due_date;

            $invoice = Invoice::create([
                'user_id'    => $service->user_id,
                'status'     => 'unpaid',
                'subtotal'   => $price,
                'total'      => $price,
                'amount_due' => $price,
                'date'       => now(),
                'due_date'   => $dueDate,
            ]);

            $invoice->items()->create([
                'service_id'  => $service->id,
                'description' => ($service->product?->name ?? 'Service').' Renewal — '.($service->domain ?? "Service #{$service->id}"),
                'quantity'    => 1,
                'unit_price'  => $price,
                'total'       => $price,
            ]);

            WorkflowEngine::fire('invoice.created', $invoice);

            // Auto-charge if user has a default saved card
            $this->tryAutoCharge($invoice, $service);

            $count++;
            $this->line("Invoice #{$invoice->id} created for service #{$service->id} ({$service->domain})");
        }

        $this->info("Generated {$count} renewal invoice(s).");

        return self::SUCCESS;
    }

    private function tryAutoCharge(Invoice $invoice, Service $service): void
    {
        $user = $service->user;

        if (! $user->stripe_customer_id) {
            return;
        }

        $defaultCard = $user->paymentMethods()->where('is_default', true)->first();

        if (! $defaultCard) {
            return;
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));

            $intent = $stripe->paymentIntents->create([
                'amount'               => (int) round($invoice->amount_due * 100),
                'currency'             => strtolower(config('app.currency', 'usd')),
                'customer'             => $user->stripe_customer_id,
                'payment_method'       => $defaultCard->stripe_payment_method_id,
                'confirm'              => true,
                'off_session'          => true,
                'description'          => "Auto-renewal: Invoice #{$invoice->id}",
            ]);

            if ($intent->status === 'succeeded') {
                $invoice->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                    'amount_due' => 0,
                ]);

                $invoice->payments()->create([
                    'user_id'                  => $user->id,
                    'amount'                   => $invoice->total,
                    'method'                   => 'stripe',
                    'stripe_payment_intent_id' => $intent->id,
                    'status'                   => 'succeeded',
                    'paid_at'                  => now(),
                ]);

                WorkflowEngine::fire('invoice.paid', $invoice->fresh());

                $this->line("  → Auto-charged successfully (intent {$intent->id})");
            }
        } catch (\Throwable $e) {
            Log::warning("Auto-charge failed for invoice #{$invoice->id}: {$e->getMessage()}");
            $this->warn("  → Auto-charge failed: {$e->getMessage()}");
        }
    }
}
