<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\TaxRate;
use App\Services\AuditLogger;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRenewalInvoices extends Command
{
    protected $signature = 'billing:generate-renewals {--days=7 : Generate for services due within this many days}';

    protected $description = 'Generate renewal invoices for services approaching their next due date.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $services = Service::with(['user', 'product'])
            ->where('status', 'active')
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->addDays($days)->toDateString())
            ->whereNull('scheduled_cancel_at')   // skip end-of-period cancellations
            ->where(fn ($q) => $q->whereNull('trial_ends_at')
                ->orWhere('trial_ends_at', '<=', now()->toDateString()))  // skip active trials
            ->whereDoesntHave('invoiceItems', fn ($q) => $q->whereHas('invoice', fn ($inv) => $inv->whereIn('status', ['unpaid', 'draft'])
            )
            )
            ->get();

        if ($services->isEmpty()) {
            $this->info('No services require renewal invoices.');

            return self::SUCCESS;
        }

        $this->info("Generating renewal invoices for {$services->count()} service(s)...");
        $created = 0;

        foreach ($services as $service) {
            try {
                DB::transaction(function () use ($service, &$created) {
                    $taxRate = TaxRate::resolveForUser($service->user);
                    $rate = $taxRate ? (float) $taxRate->rate : 0;
                    $subtotal = (float) $service->amount;
                    $tax = round($subtotal * ($rate / 100), 2);
                    $total = $subtotal + $tax;

                    $label = $service->domain
                        ? "Renewal: {$service->domain}"
                        : "Renewal: {$service->product->name}";

                    $invoice = Invoice::create([
                        'user_id' => $service->user_id,
                        'status' => 'unpaid',
                        'subtotal' => $subtotal,
                        'tax_rate' => $rate,
                        'tax' => $tax,
                        'total' => $total,
                        'amount_due' => $total,
                        'date' => now()->toDateString(),
                        'due_date' => $service->next_due_date,
                        'notes' => 'Auto-generated renewal invoice.',
                    ]);

                    $invoice->items()->create([
                        'service_id' => $service->id,
                        'description' => $label,
                        'quantity' => 1,
                        'unit_price' => $subtotal,
                        'total' => $subtotal,
                    ]);

                    AuditLogger::log('invoice.auto_generated', $invoice, ['service_id' => $service->id]);
                    WorkflowEngine::fire('invoice.created', $invoice);

                    $created++;
                    $this->line("  Created Invoice #{$invoice->id} for {$service->user->email} (due {$service->next_due_date})");
                });
            } catch (\Throwable $e) {
                $this->error("  Service #{$service->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("Done. {$created} invoice(s) created.");

        // ── Addon Renewals ─────────────────────────────────────────────────────
        $addons = ServiceAddon::with(['service.user', 'addon'])
            ->where('status', 'active')
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->addDays($days)->toDateString())
            ->whereDoesntHave('invoiceItems', fn ($q) => $q->whereHas('invoice', fn ($inv) => $inv->whereIn('status', ['unpaid', 'draft'])
            )
            )
            ->get();

        if ($addons->isNotEmpty()) {
            $this->info("Generating addon renewal invoices for {$addons->count()} addon(s)...");
            $addonCreated = 0;

            foreach ($addons as $sa) {
                try {
                    DB::transaction(function () use ($sa, &$addonCreated) {
                        $invoice = Invoice::create([
                            'user_id' => $sa->service->user_id,
                            'status' => 'unpaid',
                            'subtotal' => $sa->amount,
                            'tax_rate' => 0,
                            'tax' => 0,
                            'total' => $sa->amount,
                            'amount_due' => $sa->amount,
                            'date' => now()->toDateString(),
                            'due_date' => $sa->next_due_date,
                            'notes' => 'Auto-generated addon renewal.',
                        ]);

                        $invoice->items()->create([
                            'service_id' => $sa->service_id,
                            'service_addon_id' => $sa->id,
                            'description' => "Addon Renewal: {$sa->addon->name}",
                            'quantity' => 1,
                            'unit_price' => $sa->amount,
                            'total' => $sa->amount,
                        ]);

                        $addonCreated++;
                        $this->line("  Created Invoice #{$invoice->id} for addon \"{$sa->addon->name}\" (due {$sa->next_due_date})");
                    });
                } catch (\Throwable $e) {
                    $this->error("  ServiceAddon #{$sa->id} failed: {$e->getMessage()}");
                }
            }

            $this->info("Done. {$addonCreated} addon invoice(s) created.");
        }

        return self::SUCCESS;
    }
}
