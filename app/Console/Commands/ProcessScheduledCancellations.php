<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\AuditLogger;
use App\Services\OrderProvisioner;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledCancellations extends Command
{
    protected $signature = 'billing:process-cancellations';

    protected $description = 'Cancel services whose end-of-period cancellation date has passed.';

    public function handle(): int
    {
        $services = Service::with(['user', 'product'])
            ->where('status', 'active')
            ->whereNotNull('scheduled_cancel_at')
            ->where('scheduled_cancel_at', '<=', now()->toDateString())
            ->get();

        if ($services->isEmpty()) {
            $this->info('No scheduled cancellations due.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($services as $service) {
            try {
                OrderProvisioner::terminate($service, 'Service cancelled at end of billing period');
            } catch (\Throwable $e) {
                Log::error("Failed to terminate service #{$service->id} on panel: ".$e->getMessage());
                // Still cancel in DB so the service is not re-billed
                $service->update([
                    'status' => 'cancelled',
                    'termination_date' => now(),
                ]);
            }

            $service->update(['scheduled_cancel_at' => null]);

            AuditLogger::log('service.cancelled', $service, ['reason' => 'end_of_period']);
            WorkflowEngine::fire('service.cancelled', $service);

            $label = $service->domain ?? $service->product?->name ?? '';
            $this->line("Cancelled service #{$service->id} ({$label})");
            $count++;
        }

        $this->info("Cancelled {$count} service(s).");

        return self::SUCCESS;
    }
}
