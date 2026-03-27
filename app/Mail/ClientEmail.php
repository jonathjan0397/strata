<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ClientEmail extends Mailable
{
    public function __construct(
        public readonly string $emailSubject,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        $appName  = \App\Models\Setting::get('company_name', config('app.name'));
        $logoPath = \App\Models\Setting::get('logo_path');
        $logoHtml = $logoPath
            ? '<img src="' . url('storage/' . $logoPath) . '" alt="' . e($appName) . '" style="max-height:48px;max-width:200px;">'
            : '<span style="font-size:20px;font-weight:700;color:#fff;">' . e($appName) . '</span>';

        $bodyHtml = nl2br(e($this->body));

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f9fafb; margin: 0; padding: 0; }
  .wrap { max-width: 600px; margin: 32px auto; }
  .header { background: #4f46e5; padding: 24px 32px; border-radius: 8px 8px 0 0; }
  .body { background: #fff; padding: 32px; border-radius: 0 0 8px 8px; border: 1px solid #e5e7eb; border-top: none; }
  .footer { text-align: center; font-size: 12px; color: #9ca3af; margin-top: 24px; }
  p { color: #374151; line-height: 1.6; margin: 0 0 16px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">{$logoHtml}</div>
  <div class="body"><p>{$bodyHtml}</p></div>
  <div class="footer">&copy; {$appName}. All rights reserved.</div>
</div>
</body>
</html>
HTML;

        return new Content(htmlString: $html);
    }
}
