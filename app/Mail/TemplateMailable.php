<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplateMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $renderedSubject;
    public string $renderedHtml;
    public string $renderedPlain;

    public function __construct(
        public readonly string $slug,
        public readonly array  $vars = [],
    ) {}

    public function envelope(): Envelope
    {
        $template = EmailTemplate::findBySlug($this->slug);

        $this->renderedSubject = $template
            ? $template->render('subject', $this->vars)
            : $this->slug;

        return new Envelope(subject: $this->renderedSubject);
    }

    public function content(): Content
    {
        $template = EmailTemplate::findBySlug($this->slug);

        if (! $template) {
            $this->renderedHtml  = '<p>No template found for: '.$this->slug.'</p>';
            $this->renderedPlain = 'No template found for: '.$this->slug;

            return new Content(htmlString: $this->renderedHtml);
        }

        $this->renderedHtml  = $this->wrapHtml($template->render('body_html', $this->vars), $template->render('subject', $this->vars));
        $this->renderedPlain = $template->render('body_plain', $this->vars) ?: strip_tags($this->renderedHtml);

        return new Content(htmlString: $this->renderedHtml);
    }

    private function wrapHtml(string $body, string $subject): string
    {
        $appName = config('app.name');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f9fafb; margin: 0; padding: 0; }
  .wrap { max-width: 600px; margin: 32px auto; }
  .header { background: #4f46e5; padding: 24px 32px; border-radius: 8px 8px 0 0; }
  .header h1 { color: #fff; font-size: 18px; margin: 0; }
  .body { background: #fff; padding: 32px; border-radius: 0 0 8px 8px; border: 1px solid #e5e7eb; border-top: none; }
  .footer { text-align: center; font-size: 12px; color: #9ca3af; margin-top: 24px; }
  p { color: #374151; line-height: 1.6; margin: 0 0 16px; }
  a { color: #4f46e5; }
  .btn { display: inline-block; background: #4f46e5; color: #fff !important; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; margin: 8px 0; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header"><h1>{$appName}</h1></div>
  <div class="body">
    {$body}
  </div>
  <div class="footer">&copy; {$appName}. All rights reserved.</div>
</div>
</body>
</html>
HTML;
    }
}
