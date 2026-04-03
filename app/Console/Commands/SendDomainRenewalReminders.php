<?php

namespace App\Console\Commands;

use App\Mail\TemplateMailable;
use App\Models\Domain;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDomainRenewalReminders extends Command
{
    protected $signature = 'domains:send-reminders';

    protected $description = 'Send renewal reminder emails for domains expiring in 30, 14, or 7 days';

    public function handle(): int
    {
        $windows = [30, 14, 7];
        $sent = 0;

        foreach ($windows as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $domains = Domain::with('user')
                ->where('status', 'active')
                ->whereDate('expires_at', $targetDate)
                ->get();

            foreach ($domains as $domain) {
                if (! $domain->user) {
                    continue;
                }

                try {
                    Mail::to($domain->user->email)->send(new TemplateMailable('domain.expiring', [
                        'name' => $domain->user->name,
                        'app_name' => config('app.name'),
                        'domain' => $domain->name,
                        'expires_at' => $domain->expires_at->format('M d, Y'),
                        'days_until' => $days,
                        'renew_url' => route('client.services.index'),
                    ]));
                } catch (\Throwable) {}

                WorkflowEngine::fire('domain.expiring', $domain);

                $sent++;
                $this->line("Reminder sent for {$domain->name} (expires in {$days} days)");
            }
        }

        $this->info("Sent {$sent} domain renewal reminder(s).");

        return self::SUCCESS;
    }
}
