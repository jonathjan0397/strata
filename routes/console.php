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
Schedule::command('billing:generate-invoices --days=14')
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
