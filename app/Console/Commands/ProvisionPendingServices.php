<?php

namespace App\Console\Commands;

use App\Mail\TemplateMailable;
use App\Models\Service;
use App\Services\ProvisionerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProvisionPendingServices extends Command
{
    protected $signature   = 'provisioning:run';
    protected $description = 'Provision pending hosting services whose invoices have been paid';

    public function handle(): int
    {
        $supportedTypes = ProvisionerService::supportedTypes();

        // Target: pending services linked to a supported module type that have a paid invoice
        $services = Service::with(['user', 'product'])
            ->where('status', 'pending')
            ->whereHas('product', fn ($q) => $q->whereIn('module', $supportedTypes))
            ->whereHas('invoiceItems.invoice', fn ($q) => $q->where('status', 'paid'))
            ->whereNotNull('domain')
            ->get();

        if ($services->isEmpty()) {
            $this->info('No pending services to provision.');
            return self::SUCCESS;
        }

        $count = 0;

        foreach ($services as $service) {
            $moduleType = $service->product?->module;
            $module     = ProvisionerService::findAvailableModule($moduleType);

            if (! $module) {
                $this->error("No active {$moduleType} module with available capacity for service #{$service->id}.");
                continue;
            }

            $provisioner = ProvisionerService::forModule($module);

            try {
                // Derive optional plan from product module_config
                $plan = $service->product?->module_config['plan'] ?? null;

                $result = $provisioner->createAccount($service->domain, $plan);

                $defaultPort = match ($moduleType) {
                    'plesk'       => 8443,
                    'directadmin' => 2222,
                    'hestia'      => 8083,
                    default       => 2083,
                };

                $service->update([
                    'status'          => 'active',
                    'username'        => $result['username'],
                    'password_enc'    => encrypt($result['password']),
                    'server_hostname' => $module->hostname,
                    'server_port'     => $module->port ?? $defaultPort,
                    'module_data'     => ['module_id' => $module->id, 'provisioned_at' => now()->toIso8601String()],
                ]);

                $module->increment('current_accounts');

                Mail::to($service->user->email)->queue(new TemplateMailable('service.activated', [
                    'name'         => $service->user->name,
                    'app_name'     => config('app.name'),
                    'service_name' => $service->product?->name ?? "Service #{$service->id}",
                    'domain'       => $service->domain,
                    'username'     => $result['username'],
                    'portal_url'   => route('client.services.show', $service->id),
                ]));

                $count++;
                $this->line("Provisioned service #{$service->id} ({$service->domain}) → {$result['username']}");

            } catch (Throwable $e) {
                $this->error("Failed to provision service #{$service->id}: {$e->getMessage()}");
                Log::error('Provisioning failed', [
                    'service_id' => $service->id,
                    'domain'     => $service->domain,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->info("Provisioned {$count} service(s).");

        return self::SUCCESS;
    }
}
