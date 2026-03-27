<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Console\Command;

class GenerateRenewalInvoices extends Command
{
    protected $signature   = 'billing:generate-invoices {--days=14 : Generate invoices for services due within N days}';
    protected $description = 'Generate renewal invoices for services approaching their due date';

    public function handle(): int
    {
        $days = (int) $this->option('days');
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

            $count++;
            $this->line("Invoice #{$invoice->id} created for service #{$service->id} ({$service->domain})");
        }

        $this->info("Generated {$count} renewal invoice(s).");

        return self::SUCCESS;
    }
}
