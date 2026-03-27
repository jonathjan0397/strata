<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Console\Command;

class ApplyLateFees extends Command
{
    protected $signature   = 'billing:apply-late-fees';
    protected $description = 'Apply late fees to overdue invoices past the configured threshold';

    public function handle(): int
    {
        $feeType   = Setting::get('late_fee_type', 'fixed');   // 'fixed' | 'percent'
        $feeAmount = (float) Setting::get('late_fee_amount', 0);
        $feeDays   = (int) Setting::get('late_fee_days', 7);   // days past due before fee applies

        if ($feeAmount <= 0) {
            $this->info('Late fees disabled (late_fee_amount = 0).');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($feeDays)->startOfDay();
        $count  = 0;

        $invoices = Invoice::where('status', 'overdue')
            ->where('due_date', '<=', $cutoff)
            ->whereDoesntHave('items', fn ($q) => $q->where('description', 'like', 'Late Fee%'))
            ->get();

        foreach ($invoices as $invoice) {
            $fee = $feeType === 'percent'
                ? round($invoice->subtotal * ($feeAmount / 100), 2)
                : $feeAmount;

            if ($fee <= 0) {
                continue;
            }

            $invoice->items()->create([
                'description' => 'Late Fee',
                'quantity'    => 1,
                'unit_price'  => $fee,
                'total'       => $fee,
            ]);

            $invoice->increment('total', $fee);
            $invoice->increment('amount_due', $fee);

            $count++;
            $this->line("Late fee \${$fee} applied to Invoice #{$invoice->id}");
        }

        $this->info("Applied late fees to {$count} invoice(s).");

        return self::SUCCESS;
    }
}
