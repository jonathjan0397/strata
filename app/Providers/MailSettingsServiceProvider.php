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
            Config::set('mail.mailers.smtp.host',       $s['mail_host']       ?? '127.0.0.1');
            Config::set('mail.mailers.smtp.port',       $s['mail_port']       ?? 25);
            Config::set('mail.mailers.smtp.username',   $s['mail_username']   ?? null);
            Config::set('mail.mailers.smtp.password',   $s['mail_password']   ?? null);
            Config::set('mail.mailers.smtp.scheme',     $s['mail_encryption'] ?? null);
        }

        if ($mailer === 'sendmail') {
            $path = $s['mail_sendmail_path'] ?? '/usr/sbin/sendmail -t -i';
            Config::set('mail.mailers.sendmail.path', $path);
        }
    }
}
