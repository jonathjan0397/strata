<?php

namespace App\Services;

use App\Mail\TemplateMailable;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\OrderItem;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

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

        $product = $service->product;
        $credentials = [];

        // Attempt auto-provisioning if a supported module is configured
        if ($product->module && in_array($product->module, ProvisionerService::supportedTypes())) {
            $pinnedId = $product->module_config['module_id'] ?? null;

            if ($pinnedId) {
                // Product is pinned to a specific server — use it or fail clearly
                $module = Module::where('id', $pinnedId)->where('active', true)->first();

                if (! $module) {
                    throw new RuntimeException("Provisioning failed: the server assigned to this product (ID: {$pinnedId}) is inactive or has been removed.");
                }

                if (! $module->hasCapacity()) {
                    throw new RuntimeException("Provisioning failed: server '{$module->name}' has reached its account limit. Update the product to use a different server or increase the limit.");
                }
            } else {
                // Auto-select any available server of this type
                $module = ProvisionerService::findAvailableModule($product->module);
            }

            if ($module) {
                $driver = ProvisionerService::forModule($module);
                $plan   = $product->module_config['plan'] ?? null;

                // Auto-create the package on the panel if it doesn't already exist
                if ($plan && ($product->module_config['auto_create_package'] ?? false)) {
                    if (! $driver->packageExists($plan)) {
                        $driver->createPackage($plan, [
                            'disk_mb'      => (int) ($product->module_config['disk_mb']      ?? 1024),
                            'bandwidth_mb' => (int) ($product->module_config['bandwidth_mb'] ?? 10240),
                        ]);
                    }
                }

                $result = $driver->createAccount($service->domain ?? '', $plan, [
                    'name' => $service->user?->name,
                    'email' => $service->user?->email,
                    'php_version' => $product->module_config['php_version'] ?? null,
                    'disk_mb' => isset($product->module_config['disk_mb']) ? (int) $product->module_config['disk_mb'] : null,
                    'bandwidth_mb' => isset($product->module_config['bandwidth_mb']) ? (int) $product->module_config['bandwidth_mb'] : null,
                    'max_domains' => isset($product->module_config['max_domains']) ? (int) $product->module_config['max_domains'] : null,
                    'max_email_accounts' => isset($product->module_config['max_email_accounts']) ? (int) $product->module_config['max_email_accounts'] : null,
                    'max_databases' => isset($product->module_config['max_databases']) ? (int) $product->module_config['max_databases'] : null,
                    'max_ftp_accounts' => isset($product->module_config['max_ftp_accounts']) ? (int) $product->module_config['max_ftp_accounts'] : null,
                ]);

                $moduleData = ['module_id' => $module->id, 'provisioned_at' => now()->toIso8601String()];
                if (! empty($result['remote_id'])) {
                    $moduleData['remote_account_id'] = $result['remote_id'];
                }

                $credentials = [
                    'username' => $result['username'],
                    'password_enc' => encrypt($result['password']),
                    'server_hostname' => $module->hostname,
                    'server_port' => $module->port,
                    'module_data' => $moduleData,
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

        try {
            static::sendActivationEmail($service, $plainPassword);
        } catch (Throwable $e) {
            Log::warning("Service activation email failed for service #{$service->id}: ".$e->getMessage());
            // Throw a typed signal so the controller can show a targeted warning
            // without treating it as a provisioning failure.
            throw new \App\Exceptions\MailSendException(
                "Service activated, but the welcome email could not be sent: ".$e->getMessage()
            );
        }
    }

    /**
     * Suspend a service: calls the panel API, updates the DB, and notifies the client.
     */
    public static function suspend(Service $service, string $reason = 'Administrative action'): void
    {
        $service->loadMissing(['user', 'product']);

        static::callPanel($service, 'suspendAccount', [$service->username ?? '', $reason]);

        $service->update(['status' => 'suspended']);

        AuditLogger::log('service.suspended', $service, ['reason' => $reason]);
        WorkflowEngine::fire('service.suspended', $service);

        static::sendLifecycleEmail($service, 'service.suspended', ['reason' => $reason]);
    }

    /**
     * Unsuspend a service: calls the panel API, updates the DB, and notifies the client.
     */
    public static function unsuspend(Service $service): void
    {
        $service->loadMissing(['user', 'product']);

        static::callPanel($service, 'unsuspendAccount', [$service->username ?? '']);

        $service->update(['status' => 'active']);

        AuditLogger::log('service.unsuspended', $service);
        WorkflowEngine::fire('service.active', $service);

        static::sendLifecycleEmail($service, 'service.reactivated', []);
    }

    /**
     * Terminate a service: calls the panel API, updates the DB, and notifies the client.
     */
    public static function terminate(Service $service, string $reason = 'Administrative action'): void
    {
        $service->loadMissing(['user', 'product']);

        static::callPanel($service, 'terminateAccount', [$service->username ?? '']);

        $service->update([
            'status'           => 'terminated',
            'termination_date' => now(),
        ]);

        AuditLogger::log('service.terminated', $service, ['reason' => $reason]);
        WorkflowEngine::fire('service.terminated', $service);

        static::sendLifecycleEmail($service, 'service.terminated', ['reason' => $reason]);
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
                    Log::error("Domain service handling failed for service #{$service->id}: ".$e->getMessage());
                }

                continue;
            }

            if ($product->autosetup !== 'on_payment') {
                continue;
            }

            try {
                static::provision($service);
            } catch (\Throwable $e) {
                Log::error("Auto-provisioning failed for service #{$service->id}: ".$e->getMessage());
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
        $driver = DomainRegistrarService::driver($domain->registrar);

        if ($domain->status === 'pending') {
            // New registration
            $years = (int) ($registrarData['years'] ?? 1);

            $result = $driver->registerDomain($domain->name, $years, $registrarData);

            $domain->update([
                'status' => 'active',
                'registered_at' => now(),
                'expires_at' => now()->addYears($years),
                'registrar_data' => array_merge($registrarData, $result['registrar_data'] ?? []),
            ]);

            $service->update([
                'status' => 'active',
                'registration_date' => now(),
            ]);

            AuditLogger::log('domain.registered', $domain, ['domain' => $domain->name]);
            WorkflowEngine::fire('domain.registered', $domain);

        } elseif ($domain->status === 'transfer_pending') {
            // Transfer initiation
            $authCode = $registrarData['auth_code'] ?? '';

            $result = $driver->transferDomain($domain->name, $authCode);

            $domain->update([
                'status' => 'transferring',
                'registrar_data' => array_merge($registrarData, [
                    'transfer_id' => $result['transfer_id'] ?? null,
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
        $user = $service->user ?? $service->loadMissing('user')->user;
        $isTransfer = $domain->status === 'transferring';

        $vars = [
            'name' => $user->name,
            'app_name' => config('app.name'),
            'domain' => $domain->name,
            'registrar' => $domain->registrar,
            'expires_at' => $domain->expires_at?->format('M d, Y') ?? '—',
            'portal_url' => route('client.domains.show', $domain->id),
        ];

        $template = $isTransfer ? 'domain.transfer_initiated' : 'domain.registered';

        try {
            Mail::to($user->email)->send(new TemplateMailable($template, $vars));
        } catch (\Throwable $e) {
            Log::warning("Domain email [{$template}] failed for user #{$user->id}: ".$e->getMessage());
        }
    }

    /**
     * Call a panel API method for a service, silently skipping if no module is configured.
     * Throws if the panel call fails so the caller can decide how to handle it.
     */
    private static function callPanel(Service $service, string $method, array $args): void
    {
        $moduleId = $service->module_data['module_id'] ?? null;

        if (! $moduleId || ! $service->username) {
            Log::debug("OrderProvisioner::{$method} — skipped (no panel) for service #{$service->id}", [
                'has_module_id' => (bool) $moduleId,
                'has_username'  => (bool) $service->username,
            ]);

            return; // no panel configured — DB-only operation
        }

        $module = Module::find($moduleId);

        if (! $module) {
            throw new RuntimeException(
                "Panel module #{$moduleId} not found — it may have been deleted. ".
                "Re-link this service to an active server before retrying."
            );
        }

        $driver = ProvisionerService::forModule($module);
        $driver->{$method}(...$args);
    }

    /**
     * Send a lifecycle notification email (suspend / reactivate / terminate).
     */
    private static function sendLifecycleEmail(Service $service, string $template, array $extra): void
    {
        $user = $service->user;

        $vars = array_merge([
            'name'         => $user->name,
            'app_name'     => config('app.name'),
            'service_name' => $service->product?->name ?? "Service #{$service->id}",
            'domain'       => $service->domain ?? '—',
            'portal_url'   => route('client.services.show', $service->id),
        ], $extra);

        try {
            Mail::to($user->email)->send(new TemplateMailable($template, $vars));
        } catch (Throwable $e) {
            Log::warning("Lifecycle email [{$template}] failed for user #{$user->id}: {$e->getMessage()}");
        }
    }

    /**
     * Resend the service welcome email to the client.
     * Used by admins when the original send failed or the client didn't receive it.
     * Throws on mail failure so the caller can surface the error.
     */
    public static function resendWelcomeEmail(Service $service): void
    {
        $service->loadMissing(['user', 'product']);
        static::sendActivationEmail($service, null);
    }

    private static function sendActivationEmail(Service $service, ?string $password): void
    {
        $user = $service->user;
        $product = $service->product;

        $ns1 = $service->server_hostname ? 'ns1.'.$service->server_hostname : '—';
        $ns2 = $service->server_hostname ? 'ns2.'.$service->server_hostname : '—';

        $vars = [
            'name' => $user->name,
            'app_name' => config('app.name'),
            'service_name' => $product?->name ?? 'Service',
            'domain' => $service->domain ?? '—',
            'username' => $service->username ?? '—',
            'password' => $password ?? '—',
            'server' => $service->server_hostname ?? '—',
            'nameserver1' => $ns1,
            'nameserver2' => $ns2,
            'portal_url' => route('client.services.show', $service->id),
        ];

        // Let the exception bubble — callers decide whether to swallow or surface it.
        Mail::to($user->email)->send(new TemplateMailable('service.active', $vars));
    }
}
