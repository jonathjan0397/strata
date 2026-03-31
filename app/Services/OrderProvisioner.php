<?php

namespace App\Services;

use App\Mail\TemplateMailable;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\AuditLogger;
use App\Services\DomainRegistrarService;
use App\Services\WorkflowEngine;

class OrderProvisioner
{
    /**
     * Provision a pending service.
     *
     * Finds an available module for the product, creates the hosting account,
     * stores credentials on the service, marks it active, fires workflows,
     * and sends the client a welcome email with login details.
     *
     * Throws on provisioner errors — callers should catch and log.
     */
    public static function provision(Service $service): void
    {
        $service->loadMissing(['user', 'product']);

        $product     = $service->product;
        $credentials = [];

        // Attempt auto-provisioning if a supported module is configured
        if ($product->module && in_array($product->module, ProvisionerService::supportedTypes())) {
            $module = ProvisionerService::findAvailableModule($product->module);

            if ($module) {
                $driver = ProvisionerService::forModule($module);
                $plan   = $product->module_config['plan'] ?? null;
                $result = $driver->createAccount($service->domain ?? '', $plan);

                $credentials = [
                    'username'        => $result['username'],
                    'password_enc'    => encrypt($result['password']),
                    'server_hostname' => $module->hostname,
                    'server_port'     => $module->port,
                    'module_data'     => ['module_id' => $module->id, 'provisioned_at' => now()->toIso8601String()],
                ];

                $module->increment('current_accounts');
            }
        }

        $service->update(array_merge($credentials, ['status' => 'active']));

        // Mark any linked orders as active
        OrderItem::where('service_id', $service->id)
            ->with('order')
            ->get()
            ->each(fn ($item) => $item->order?->update(['status' => 'active']));

        AuditLogger::log('service.activated', $service);
        WorkflowEngine::fire('service.active', $service);

        // Send welcome email — pass plain-text password if we provisioned
        $plainPassword = isset($credentials['password_enc'])
            ? decrypt($credentials['password_enc'])
            : null;

        static::sendActivationEmail($service, $plainPassword);
    }

    /**
     * After an invoice is paid, provision any pending services linked to it
     * whose product has autosetup = 'on_payment'.
     */
    public static function handleInvoicePaid(Invoice $invoice): void
    {
        $invoice->loadMissing(['items.service.product']);

        $seen = [];

        foreach ($invoice->items as $item) {
            if (! $item->service_id || in_array($item->service_id, $seen)) {
                continue;
            }

            $seen[] = $item->service_id;

            $service = $item->service;

            if (! $service || $service->status !== 'pending') {
                continue;
            }

            $product = $service->product;

            // Domain registration or transfer (no product_id)
            if ($product === null) {
                try {
                    static::handleDomainService($service);
                } catch (\Throwable $e) {
                    Log::error("Domain service handling failed for service #{$service->id}: " . $e->getMessage());
                }
                continue;
            }

            if ($product->autosetup !== 'on_payment') {
                continue;
            }

            try {
                static::provision($service);
            } catch (\Throwable $e) {
                Log::error("Auto-provisioning failed for service #{$service->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Register or initiate transfer of a domain service after invoice payment.
     */
    private static function handleDomainService(Service $service): void
    {
        $domain = Domain::where('service_id', $service->id)->first();

        if (! $domain) {
            return;
        }

        $registrarData = $domain->registrar_data ?? [];
        $driver        = DomainRegistrarService::driver($domain->registrar);

        if ($domain->status === 'pending') {
            // New registration
            $years = (int) ($registrarData['years'] ?? 1);

            $result = $driver->registerDomain($domain->name, $years, $registrarData);

            $domain->update([
                'status'         => 'active',
                'registered_at'  => now(),
                'expires_at'     => now()->addYears($years),
                'registrar_data' => array_merge($registrarData, $result['registrar_data'] ?? []),
            ]);

            $service->update([
                'status'            => 'active',
                'registration_date' => now(),
            ]);

            AuditLogger::log('domain.registered', $domain, ['domain' => $domain->name]);
            WorkflowEngine::fire('domain.registered', $domain);

        } elseif ($domain->status === 'transfer_pending') {
            // Transfer initiation
            $authCode = $registrarData['auth_code'] ?? '';

            $result = $driver->transferDomain($domain->name, $authCode);

            $domain->update([
                'status'         => 'transferring',
                'registrar_data' => array_merge($registrarData, [
                    'transfer_id'           => $result['transfer_id'] ?? null,
                    'transfer_initiated_at' => now()->toIso8601String(),
                ]),
            ]);

            // Service goes active — billing has been paid; domain transfer completes async
            $service->update(['status' => 'active']);

            AuditLogger::log('domain.transfer_initiated', $domain, ['domain' => $domain->name]);
            WorkflowEngine::fire('domain.transfer_initiated', $domain);
        }

        static::sendDomainEmail($service, $domain);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private static function sendDomainEmail(Service $service, Domain $domain): void
    {
        $user      = $service->user ?? $service->loadMissing('user')->user;
        $isTransfer = $domain->status === 'transferring';

        $vars = [
            'name'       => $user->name,
            'app_name'   => config('app.name'),
            'domain'     => $domain->name,
            'registrar'  => $domain->registrar,
            'expires_at' => $domain->expires_at?->format('M d, Y') ?? '—',
            'portal_url' => route('client.domains.show', $domain->id),
        ];

        $template = $isTransfer ? 'domain.transfer_initiated' : 'domain.registered';

        try {
            Mail::to($user->email)->send(new TemplateMailable($template, $vars));
        } catch (\Throwable) {
            // mail failure must not block domain activation
        }
    }

    private static function sendActivationEmail(Service $service, ?string $password): void
    {
        $user    = $service->user;
        $product = $service->product;

        $ns1 = $service->server_hostname ? 'ns1.' . $service->server_hostname : '—';
        $ns2 = $service->server_hostname ? 'ns2.' . $service->server_hostname : '—';

        $vars = [
            'name'         => $user->name,
            'app_name'     => config('app.name'),
            'service_name' => $product?->name ?? 'Service',
            'domain'       => $service->domain ?? '—',
            'username'     => $service->username ?? '—',
            'password'     => $password ?? '—',
            'server'       => $service->server_hostname ?? '—',
            'nameserver1'  => $ns1,
            'nameserver2'  => $ns2,
            'portal_url'   => route('client.services.show', $service->id),
        ];

        try {
            Mail::to($user->email)->send(new TemplateMailable('service.active', $vars));
        } catch (\Throwable) {
            // mail failure must not block service activation
        }
    }
}
