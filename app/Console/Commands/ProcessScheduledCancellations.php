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

        $count       = 0;
        $panelFailed = [];

        foreach ($services as $index => $service) {
            // Space out panel API calls to avoid rate-limiting on bulk operations.
            if ($index > 0 && ($service->module_data['module_id'] ?? null) && $service->username) {
                sleep(1);
            }

            try {
                OrderProvisioner::terminate($service, 'Service cancelled at end of billing period');
                $this->line("  ✓ Terminated service #{$service->id} ({$service->domain})");
            } catch (\Throwable $e) {
                Log::error("Failed to terminate service #{$service->id} on panel: ".$e->getMessage());
                // Still cancel in DB so the service is not re-billed.
                $service->update([
                    'status' => 'cancelled',
                    'termination_date' => now(),
                ]);
                $panelFailed[] = "#{$service->id} ({$service->domain}): ".$e->getMessage();
                $this->warn("  ⚠ DB-only — panel call failed for service #{$service->id}: ".$e->getMessage());
            }

            $service->update(['scheduled_cancel_at' => null]);

            AuditLogger::log('service.cancelled', $service, ['reason' => 'end_of_period']);
            WorkflowEngine::fire('service.cancelled', $service);

            $count++;
        }

        $this->info("Cancelled {$count} service(s).");

        if (count($panelFailed) > 0) {
            $this->warn(count($panelFailed).' panel call(s) failed — services cancelled in DB only:');
            foreach ($panelFailed as $msg) {
                $this->warn("  - {$msg}");
            }
            Log::warning('ProcessScheduledCancellations: '.count($panelFailed).' panel call(s) failed.', $panelFailed);
        }

        return self::SUCCESS;
    }
}
