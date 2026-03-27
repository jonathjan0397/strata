<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Invoice;
use App\Services\DomainRegistrarService;
use Illuminate\Console\Command;

class RenewExpiringDomains extends Command
{
    protected $signature = 'domains:renew-expiring {--days=30 : Renew domains expiring within this many days}';

    protected $description = 'Auto-renew domains expiring within the configured window';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $domains = Domain::where('auto_renew', true)
            ->where('status', 'active')
            ->whereDate('expires_at', '<=', now()->addDays($days))
            ->get();

        if ($domains->isEmpty()) {
            $this->info('No domains due for renewal.');
            return self::SUCCESS;
        }

        foreach ($domains as $domain) {
            try {
                $driver = DomainRegistrarService::driver($domain->registrar);
                $result = $driver->renewDomain($domain->name, 1);

                $domain->update([
                    'status'     => 'active',
                    'expires_at' => $result['expires_at'] ?: $domain->expires_at?->addYear(),
                ]);

                $this->info("Renewed: {$domain->name}");
            } catch (\Exception $e) {
                $this->error("Failed to renew {$domain->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
