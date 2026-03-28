<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class MailSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $s = Setting::allKeyed();
        } catch (\Throwable) {
            return;
        }

        $mailer = $s['mail_mailer'] ?? null;
        if (! $mailer) {
            return;
        }

        Config::set('mail.default', $mailer);

        Config::set('mail.from.address', $s['mail_from_address'] ?? config('mail.from.address'));
        Config::set('mail.from.name',    $s['mail_from_name']    ?? config('mail.from.name'));

        if ($mailer === 'smtp') {
            $port       = (int) ($s['mail_port'] ?? 25);
            $encryption = $s['mail_encryption'] ?? 'auto';

            // Resolve 'auto' to the correct Symfony Mailer scheme based on port.
            // 465        → 'smtps'  (implicit TLS — no STARTTLS negotiation)
            // 587, 2525  → 'smtp'   (Symfony will use STARTTLS when available)
            // 25         → null     (opportunistic — STARTTLS if offered, else plain)
            // anything else on 'auto' → null (let Symfony negotiate)
            if ($encryption === 'auto') {
                $scheme = match ($port) {
                    465       => 'smtps',
                    587, 2525 => 'smtp',
                    default   => null,
                };
            } elseif ($encryption === 'ssl') {
                $scheme = 'smtps';
            } elseif ($encryption === 'tls') {
                $scheme = 'smtp';
            } else {
                $scheme = null; // 'none' / plain
            }

            Config::set('mail.mailers.smtp.host',     $s['mail_host']   ?? '127.0.0.1');
            Config::set('mail.mailers.smtp.port',     $port);
            Config::set('mail.mailers.smtp.username', $s['mail_username'] ?? null);
            Config::set('mail.mailers.smtp.password', $s['mail_password'] ?? null);
            Config::set('mail.mailers.smtp.scheme',   $scheme);
        }

        if ($mailer === 'sendmail') {
            $path = $s['mail_sendmail_path'] ?? '/usr/sbin/sendmail -t -i';
            Config::set('mail.mailers.sendmail.path', $path);
        }
    }
}
