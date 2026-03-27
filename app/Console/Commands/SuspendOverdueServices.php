<?php

namespace App\Console\Commands;

use App\Models\Service;
use Illuminate\Console\Command;

class SuspendOverdueServices extends Command
{
    protected $signature   = 'billing:suspend-overdue {--grace=3 : Days past due before suspending}';
    protected $description = 'Suspend active services whose invoices are overdue beyond the grace period';

    public function handle(): int
    {
        $grace   = (int) $this->option('grace');
        $cutoff  = now()->subDays($grace)->startOfDay();
        $count   = 0;

        // Find active services that have at least one overdue invoice older than the grace period
        $services = Service::where('status', 'active')
            ->whereHas('invoiceItems.invoice', fn ($q) =>
                $q->where('status', 'overdue')
                  ->where('due_date', '<=', $cutoff)
            )
            ->get();

        foreach ($services as $service) {
            $service->update(['status' => 'suspended']);
            $count++;
            $this->line("Suspended service #{$service->id} ({$service->domain})");
        }

        $this->info("Suspended {$count} service(s).");

        return self::SUCCESS;
    }
}
