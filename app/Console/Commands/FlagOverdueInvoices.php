<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class FlagOverdueInvoices extends Command
{
    protected $signature   = 'billing:flag-overdue';
    protected $description = 'Mark unpaid past-due invoices as overdue';

    public function handle(): int
    {
        $count = Invoice::where('status', 'unpaid')
            ->where('due_date', '<', now()->startOfDay())
            ->update(['status' => 'overdue']);

        $this->info("Flagged {$count} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}
