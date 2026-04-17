<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // General
            'company_name' => config('app.name', 'Strata Service Billing and Support Platform'),
            'timezone' => 'UTC',
            'date_format' => 'M d, Y',

            // Company contact
            'company_email' => '',
            'company_phone' => '',
            'company_address' => '',
            'company_city' => '',
            'company_state' => '',
            'company_zip' => '',
            'company_country' => '',

            // Billing
            'currency' => 'USD',
            'currency_symbol' => '$',
            'invoice_prefix' => 'INV-',
            'invoice_due_days' => '7',
            'grace_period_days' => '3',
            'tax_rate' => '0',
            'tax_name' => 'Tax',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
