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

        // Domain registrar
        if ($v = $s['integration_registrar_driver']    ?? null) Config::set('registrars.default',                  $v);
        if ($v = $s['integration_namecheap_api_user']  ?? null) Config::set('registrars.namecheap.api_user',        $v);
        if ($v = $s['integration_namecheap_api_key']   ?? null) Config::set('registrars.namecheap.api_key',         $v);
        if ($v = $s['integration_namecheap_client_ip'] ?? null) Config::set('registrars.namecheap.client_ip',       $v);
        if (isset($s['integration_namecheap_sandbox']))          Config::set('registrars.namecheap.sandbox',         (bool) $s['integration_namecheap_sandbox']);
        if ($v = $s['integration_enom_uid']            ?? null) Config::set('registrars.enom.uid',                  $v);
        if ($v = $s['integration_enom_pw']             ?? null) Config::set('registrars.enom.pw',                   $v);
        if (isset($s['integration_enom_sandbox']))               Config::set('registrars.enom.sandbox',              (bool) $s['integration_enom_sandbox']);
        if ($v = $s['integration_opensrs_api_key']     ?? null) Config::set('registrars.opensrs.api_key',           $v);
        if ($v = $s['integration_opensrs_reseller_username'] ?? null) Config::set('registrars.opensrs.reseller_username', $v);
        if (isset($s['integration_opensrs_sandbox']))            Config::set('registrars.opensrs.sandbox',           (bool) $s['integration_opensrs_sandbox']);
        if ($v = $s['integration_hexonet_login']       ?? null) Config::set('registrars.hexonet.login',             $v);
        if ($v = $s['integration_hexonet_password']    ?? null) Config::set('registrars.hexonet.password',          $v);
        if (isset($s['integration_hexonet_sandbox']))            Config::set('registrars.hexonet.sandbox',           (bool) $s['integration_hexonet_sandbox']);

        // Two-Factor Authentication (Google2FA)
        if (isset($s['otp_enabled']))    Config::set('google2fa.enabled',    (bool) $s['otp_enabled']);
        if (isset($s['otp_lifetime']))   Config::set('google2fa.lifetime',   (int)  $s['otp_lifetime']);
        if (isset($s['otp_keep_alive'])) Config::set('google2fa.keep_alive', (bool) $s['otp_keep_alive']);
    }
}
