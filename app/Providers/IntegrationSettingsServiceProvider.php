<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class IntegrationSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $s = Setting::allKeyed();
        } catch (\Throwable) {
            return;
        }

        // Google OAuth
        if ($v = $s['integration_google_client_id']     ?? null) Config::set('services.google.client_id',     $v);
        if ($v = $s['integration_google_client_secret'] ?? null) Config::set('services.google.client_secret', $v);

        // Microsoft OAuth
        if ($v = $s['integration_microsoft_client_id']     ?? null) Config::set('services.microsoft.client_id',     $v);
        if ($v = $s['integration_microsoft_client_secret'] ?? null) Config::set('services.microsoft.client_secret', $v);
        if ($v = $s['integration_microsoft_tenant']        ?? null) Config::set('services.microsoft.tenant',        $v);

        // Stripe
        if ($v = $s['integration_stripe_key']            ?? null) Config::set('services.stripe.key',            $v);
        if ($v = $s['integration_stripe_secret']         ?? null) Config::set('services.stripe.secret',         $v);
        if ($v = $s['integration_stripe_webhook_secret'] ?? null) Config::set('services.stripe.webhook_secret', $v);

        // PayPal
        if ($v = $s['integration_paypal_client_id']     ?? null) Config::set('services.paypal.client_id',     $v);
        if ($v = $s['integration_paypal_client_secret'] ?? null) Config::set('services.paypal.client_secret', $v);
        if (isset($s['integration_paypal_mode']))                 Config::set('services.paypal.mode',          $s['integration_paypal_mode'] ?: 'sandbox');

        // Authorize.Net
        if ($v = $s['integration_authorizenet_login_id']       ?? null) Config::set('services.authorizenet.login_id',       $v);
        if ($v = $s['integration_authorizenet_transaction_key']?? null) Config::set('services.authorizenet.transaction_key', $v);
        if ($v = $s['integration_authorizenet_client_key']     ?? null) Config::set('services.authorizenet.client_key',      $v);
        if (isset($s['integration_authorizenet_sandbox']))               Config::set('services.authorizenet.sandbox',         (bool) $s['integration_authorizenet_sandbox']);
    }
}
