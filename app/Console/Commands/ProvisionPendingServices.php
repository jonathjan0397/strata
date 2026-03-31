<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\OrderProvisioner;
use App\Services\ProvisionerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProvisionPendingServices extends Command
{
    protected $signature = 'provisioning:run';

    protected $description = 'Provision pending on_payment services whose invoices have been paid.';

    public function handle(): int
    {
        $supportedTypes = ProvisionerService::supportedTypes();

        // Target: pending services with autosetup=on_payment, supported module,
        // and at least one paid invoice linked via invoice items.
        $services = Service::with(['user', 'product'])
            ->where('status', 'pending')
            ->whereHas('product', fn ($q) => $q->where('autosetup', 'on_payment')
                ->whereIn('module', $supportedTypes)
            )
            ->whereHas('invoiceItems.invoice', fn ($q) => $q->where('status', 'paid'))
            ->whereNotNull('domain')
            ->get();

        if ($services->isEmpty()) {
            $this->info('No pending services to provision.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($services as $service) {
            try {
                OrderProvisioner::provision($service);
                $count++;
                $this->line("Provisioned service #{$service->id} ({$service->domain})");
            } catch (\Throwable $e) {
                $this->error("Failed to provision service #{$service->id}: {$e->getMessage()}");
                Log::error('Provisioning failed', [
                    'service_id' => $service->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Provisioned {$count} service(s).");

        return self::SUCCESS;
    }
}
