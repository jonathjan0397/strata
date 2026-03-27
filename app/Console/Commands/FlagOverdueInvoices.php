<?php

namespace App\Console\Commands;

use App\Mail\TemplateMailable;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class FlagOverdueInvoices extends Command
{
    protected $signature   = 'billing:flag-overdue';
    protected $description = 'Mark unpaid past-due invoices as overdue and notify clients';

    public function handle(): int
    {
        $invoices = Invoice::with('user')
            ->where('status', 'unpaid')
            ->where('due_date', '<', now()->startOfDay())
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->update(['status' => 'overdue']);

            Mail::to($invoice->user->email)->queue(new TemplateMailable('invoice.overdue', [
                'name'        => $invoice->user->name,
                'app_name'    => config('app.name'),
                'invoice_id'  => $invoice->id,
                'amount'      => number_format((float) $invoice->amount_due, 2),
                'due_date'    => $invoice->due_date->format('M d, Y'),
                'invoice_url' => route('client.invoices.show', $invoice->id),
            ]));
        }

        $this->info("Flagged {$invoices->count()} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}
