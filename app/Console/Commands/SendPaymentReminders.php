<?php

namespace App\Console\Commands;

use App\Mail\TemplateMailable;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminders extends Command
{
    protected $signature = 'billing:send-reminders';

    protected $description = 'Send payment reminder emails for upcoming and overdue invoices';

    public function handle(): int
    {
        // Comma-separated days before due date to send reminders (e.g. "7,3,1")
        $reminderDays = array_filter(
            array_map('intval', explode(',', Setting::get('reminder_days', '7,3,1')))
        );

        $sent = 0;

        foreach ($reminderDays as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $invoices = Invoice::with('user')
                ->where('status', 'unpaid')
                ->whereDate('due_date', $targetDate)
                ->get();

            foreach ($invoices as $invoice) {
                Mail::to($invoice->user->email)->queue(new TemplateMailable('invoice.reminder', [
                    'name' => $invoice->user->name,
                    'app_name' => config('app.name'),
                    'invoice_id' => $invoice->id,
                    'amount' => number_format((float) $invoice->amount_due, 2),
                    'due_date' => $invoice->due_date->format('M d, Y'),
                    'days_until' => $days,
                    'invoice_url' => route('client.invoices.show', $invoice->id),
                ]));

                $sent++;
            }
        }

        $this->info("Sent {$sent} payment reminder(s).");

        return self::SUCCESS;
    }
}
