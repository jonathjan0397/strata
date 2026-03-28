<?php

namespace App\Console\Commands;

use App\Mail\TemplateMailable;
use App\Models\SupportTicket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CloseInactiveTickets extends Command
{
    protected $signature   = 'support:close-inactive {--days= : Override the inactivity threshold in days}';
    protected $description = 'Close open tickets with no activity after N days (configurable via settings)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? \App\Models\Setting::get('ticket_auto_close_days', 14));

        if ($days <= 0) {
            $this->info('Auto-close disabled (days <= 0).');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        // Find tickets that are open/answered/customer_reply and have had no
        // reply (or update) since the cutoff. We join to the latest reply time.
        $tickets = SupportTicket::with('user')
            ->whereIn('status', ['open', 'answered', 'customer_reply'])
            ->where(function ($q) use ($cutoff) {
                // Either the ticket itself hasn't been updated since cutoff...
                $q->where('updated_at', '<=', $cutoff)
                  // ...and has no replies newer than cutoff
                  ->whereDoesntHave('replies', fn ($r) => $r->where('created_at', '>', $cutoff));
            })
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->update(['status' => 'closed', 'closed_at' => now()]);

            // Notify the client that their ticket was auto-closed
            try {
                Mail::to($ticket->user->email)->send(new TemplateMailable('support.closed', [
                    'name'       => $ticket->user->name,
                    'app_name'   => config('app.name'),
                    'ticket_id'  => $ticket->id,
                    'subject'    => $ticket->subject,
                    'ticket_url' => route('client.support.show', $ticket->id),
                ]));
            } catch (\Throwable) {}
        }

        $this->info("Auto-closed {$tickets->count()} ticket(s) inactive for {$days}+ days.");

        return self::SUCCESS;
    }
}
