<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class RetryFailedPayments extends Command
{
    protected $signature   = 'billing:retry-payments';
    protected $description = 'Retry auto-charge for overdue invoices with a saved payment method (dunning)';

    public function handle(): int
    {
        $maxAttempts = (int) Setting::get('dunning_max_attempts', 3);
        $retryDays   = array_filter(
            array_map('intval', explode(',', Setting::get('dunning_retry_days', '1,3,7')))
        );

        if ($maxAttempts <= 0) {
            $this->info('Dunning disabled (dunning_max_attempts = 0).');
            return self::SUCCESS;
        }

        $charged = 0;
        $failed  = 0;

        $invoices = Invoice::with(['user.paymentMethods'])
            ->where('status', 'overdue')
            ->where('dunning_attempts', '<', $maxAttempts)
            ->where(function ($q) use ($retryDays) {
                // Either never attempted, or last attempt was N+ days ago matching the retry schedule
                $q->whereNull('dunning_last_attempt_at');
                $attemptNumber = 1;
                foreach ($retryDays as $days) {
                    $cutoff = now()->subDays($days);
                    $q->orWhere(function ($sub) use ($cutoff, $attemptNumber) {
                        $sub->where('dunning_attempts', $attemptNumber)
                            ->where('dunning_last_attempt_at', '<=', $cutoff);
                    });
                    $attemptNumber++;
                }
            })
            ->get();

        foreach ($invoices as $invoice) {
            $user = $invoice->user;

            if (! $user?->stripe_customer_id) {
                continue;
            }

            $defaultCard = $user->paymentMethods->firstWhere('is_default', true);

            if (! $defaultCard) {
                continue;
            }

            // Track attempt before charging
            $invoice->increment('dunning_attempts');
            $invoice->update(['dunning_last_attempt_at' => now()]);

            try {
                $stripe = new StripeClient(config('services.stripe.secret'));

                $intent = $stripe->paymentIntents->create([
                    'amount'         => (int) round($invoice->amount_due * 100),
                    'currency'       => strtolower(config('app.currency', 'usd')),
                    'customer'       => $user->stripe_customer_id,
                    'payment_method' => $defaultCard->stripe_payment_method_id,
                    'confirm'        => true,
                    'off_session'    => true,
                    'description'    => "Dunning retry #{$invoice->dunning_attempts}: Invoice #{$invoice->id}",
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

                    $charged++;
                    $this->line("✓ Invoice #{$invoice->id} charged on attempt #{$invoice->dunning_attempts}");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("Dunning retry failed for Invoice #{$invoice->id}: {$e->getMessage()}");
                $this->warn("✗ Invoice #{$invoice->id} retry failed: {$e->getMessage()}");
            }
        }

        $this->info("Dunning: {$charged} charged, {$failed} failed, " . count($invoices) . " attempted.");

        return self::SUCCESS;
    }
}
