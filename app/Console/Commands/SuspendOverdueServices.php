<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\Setting;
use App\Services\OrderProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SuspendOverdueServices extends Command
{
    protected $signature = 'billing:suspend-overdue {--grace=3 : Days past due before suspending}';

    protected $description = 'Suspend active services whose invoices are overdue beyond the grace period';

    public function handle(): int
    {
        $grace = $this->option('grace') !== '3'
            ? (int) $this->option('grace')
            : (int) Setting::get('grace_period_days', 3);
        $cutoff = now()->subDays($grace)->startOfDay();
        $count = 0;

        $services = Service::with(['user', 'product'])
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('trial_ends_at')
                ->orWhere('trial_ends_at', '<=', now()->toDateString()))  // never suspend active trial services
            ->whereHas('invoiceItems.invoice', fn ($q) => $q->where('status', 'overdue')
                ->where('due_date', '<=', $cutoff)
            )
            ->get();

        foreach ($services as $service) {
            try {
                OrderProvisioner::suspend($service, 'Payment overdue');
            } catch (\Throwable $e) {
                Log::error("Failed to suspend service #{$service->id} on panel: ".$e->getMessage());
                // Still mark suspended in DB so billing flow continues
                $service->update(['status' => 'suspended']);
            }

            $count++;
            $this->line("Suspended service #{$service->id} ({$service->domain})");
        }

        $this->info("Suspended {$count} service(s).");

        return self::SUCCESS;
    }
}
