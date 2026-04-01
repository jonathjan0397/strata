<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Billing Automation Schedule
|--------------------------------------------------------------------------
*/

// Generate renewal invoices daily at 08:00 for services due within 14 days
Schedule::command('billing:generate-renewals --days=14')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();

// Flag past-due unpaid invoices as overdue — runs daily at 00:05
Schedule::command('billing:flag-overdue')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->runInBackground();

// Suspend services with invoices overdue beyond the 3-day grace period — runs daily at 01:00
Schedule::command('billing:suspend-overdue --grace=3')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

// Provision paid pending cPanel services — runs every 5 minutes
Schedule::command('provisioning:run')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Auto-renew domains expiring within 30 days — runs daily at 09:00
Schedule::command('domains:renew-expiring --days=30')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();

// Send payment reminders for invoices due in 7, 3, and 1 day(s) — runs daily at 10:00
Schedule::command('billing:send-reminders')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->runInBackground();

// Auto-close support tickets inactive for N days — runs daily at 03:00
Schedule::command('support:close-inactive')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Send domain renewal reminder emails at 30/14/7 days before expiry — runs daily at 09:30
Schedule::command('domains:send-reminders')
    ->dailyAt('09:30')
    ->withoutOverlapping()
    ->runInBackground();

// Apply late fees to overdue invoices past the configured threshold — runs daily at 02:00
Schedule::command('billing:apply-late-fees')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Dunning: retry auto-charge on overdue invoices with saved payment methods — runs daily at 11:00
Schedule::command('billing:retry-payments')
    ->dailyAt('11:00')
    ->withoutOverlapping()
    ->runInBackground();

// Cancel services whose end-of-period cancellation date has been reached — runs daily at 00:30
Schedule::command('billing:process-cancellations')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->runInBackground();

// Platform telemetry / license sync — every 12 hours at 04:15 and 16:15
Schedule::command('strata:sync')
    ->twiceDaily(4, 16)
    ->withoutOverlapping()
    ->runInBackground();
