<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $settings;

    public function __construct(public Invoice $invoice)
    {
        $this->invoice->loadMissing(['user', 'items', 'payments']);

        $this->settings = [
            'company_name' => Setting::get('company_name', config('app.name')),
            'company_address' => Setting::get('company_address', ''),
            'currency_symbol' => Setting::get('currency_symbol', '$'),
            'logo_path' => Setting::get('logo_path'),
        ];
    }

    public function envelope(): Envelope
    {
        $prefix = match ($this->invoice->status) {
            'paid' => 'Receipt',
            'overdue' => 'OVERDUE: Invoice',
            default => 'Invoice',
        };

        $company = $this->settings['company_name'];

        return new Envelope(
            subject: "{$prefix} #{$this->invoice->id} from {$company}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'settings' => $this->settings,
                'currencySymbol' => $this->settings['currency_symbol'],
            ],
        );
    }
}
