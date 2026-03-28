<?php

namespace App\Console\Commands;

use App\Mail\TemplateMailable;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SuspendOverdueServices extends Command
{
    protected $signature   = 'billing:suspend-overdue {--grace=3 : Days past due before suspending}';
    protected $description = 'Suspend active services whose invoices are overdue beyond the grace period';

    public function handle(): int
    {
        $grace  = $this->option('grace') !== '3'
            ? (int) $this->option('grace')
            : (int) Setting::get('grace_period_days', 3);
        $cutoff = now()->subDays($grace)->startOfDay();
        $count  = 0;

        $services = Service::with(['user', 'product'])
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('trial_ends_at')
                ->orWhere('trial_ends_at', '<=', now()->toDateString()))  // never suspend active trial services
            ->whereHas('invoiceItems.invoice', fn ($q) =>
                $q->where('status', 'overdue')
                  ->where('due_date', '<=', $cutoff)
            )
            ->get();

        foreach ($services as $service) {
            $service->update(['status' => 'suspended']);

            Mail::to($service->user->email)->queue(new TemplateMailable('service.suspended', [
                'name'         => $service->user->name,
                'app_name'     => config('app.name'),
                'service_name' => $service->product?->name ?? "Service #{$service->id}",
                'domain'       => $service->domain ?? '',
                'invoices_url' => route('client.invoices.index'),
            ]));

            $count++;
            $this->line("Suspended service #{$service->id} ({$service->domain})");
        }

        $this->info("Suspended {$count} service(s).");

        return self::SUCCESS;
    }
}
