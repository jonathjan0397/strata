<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\ProvisionerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ServerImportController extends Controller
{
    /** Show the import wizard for a given module. */
    public function show(Module $module): Response|RedirectResponse
    {
        if (! in_array($module->type, ProvisionerService::supportedTypes())) {
            return redirect()->route('admin.modules.index')
                ->with('flash', ['error' => "Import is not supported for '{$module->type}' servers."]);
        }

        return Inertia::render('Admin/Modules/Import', [
            'module'   => $module->only('id', 'name', 'type', 'hostname'),
            'products' => Product::orderBy('name')
                ->get(['id', 'name', 'type', 'billing_cycle', 'price', 'module', 'module_config']),
        ]);
    }

    /**
     * Fetch accounts and packages from the panel and return enriched data.
     * Called via AJAX from the wizard.
     */
    public function preview(Module $module): JsonResponse
    {
        try {
            $driver   = ProvisionerService::forModule($module);
            $accounts = $driver->listAccounts();
            $packages = $driver->listPackages();
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        // Build package → product auto-match map
        $products    = Product::orderBy('name')->get(['id', 'name', 'module_config']);
        $packageMap  = [];

        foreach ($packages as $pkg) {
            $name    = $pkg['name'];
            $matched = $products->first(function (Product $p) use ($name) {
                $cfg = $p->module_config ?? [];
                return isset($cfg['plan']) && strcasecmp($cfg['plan'], $name) === 0;
            }) ?? $products->first(fn (Product $p) => strcasecmp($p->name, $name) === 0);

            $packageMap[$name] = $matched?->id;
        }

        // Enrich accounts: flag already-imported ones and match existing clients
        $importedUsernames = Service::whereNotNull('username')
            ->pluck('username')
            ->flip()
            ->toArray();

        $emailIndex = User::whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->pluck('id', 'email')
            ->toArray();

        $enriched = array_map(function (array $acct) use ($importedUsernames, $emailIndex) {
            return array_merge($acct, [
                'already_imported'   => isset($importedUsernames[$acct['username']]),
                'existing_client_id' => ! empty($acct['email']) ? ($emailIndex[$acct['email']] ?? null) : null,
            ]);
        }, $accounts);

        return response()->json([
            'accounts'    => $enriched,
            'packages'    => $packages,
            'package_map' => $packageMap,
        ]);
    }

    /**
     * Run the import.
     *
     * Expects JSON body:
     * {
     *   accounts: [{username, domain, email, plan, suspended}],
     *   package_map: {planName: productId|null},
     *   auto_create_products: ['PlanName', ...],  // plans to auto-create as products
     * }
     */
    public function store(Request $request, Module $module): JsonResponse
    {
        $data = $request->validate([
            'accounts'                  => ['required', 'array'],
            'accounts.*.username'       => ['required', 'string', 'max:64'],
            'accounts.*.domain'         => ['required', 'string', 'max:253'],
            'accounts.*.email'          => ['nullable', 'string', 'max:255'],
            'accounts.*.plan'           => ['nullable', 'string', 'max:100'],
            'accounts.*.suspended'      => ['boolean'],
            'package_map'               => ['required', 'array'],
            'package_map.*'             => ['nullable', 'integer', 'exists:products,id'],
            'auto_create_products'      => ['array'],
            'auto_create_products.*'    => ['string', 'max:100'],
        ]);

        $imported       = 0;
        $skipped        = 0;
        $newClients     = 0;
        $newProducts    = 0;
        $errors         = [];

        DB::beginTransaction();

        try {
            // Resolve / create products for unmapped plans
            $resolvedMap = [];

            foreach ($data['package_map'] as $planName => $productId) {
                if ($productId) {
                    $resolvedMap[$planName] = (int) $productId;
                }
            }

            foreach ($data['auto_create_products'] ?? [] as $planName) {
                if (isset($resolvedMap[$planName])) {
                    continue;
                }

                $product = Product::create([
                    'name'          => $planName,
                    'type'          => 'shared',
                    'price'         => 0,
                    'setup_fee'     => 0,
                    'billing_cycle' => 'monthly',
                    'module'        => $module->type,
                    'module_config' => ['plan' => $planName],
                    'autosetup'     => 'on_payment',
                    'hidden'        => true,  // hidden until admin reviews and prices it
                    'taxable'       => true,
                    'sort_order'    => 0,
                ]);

                $resolvedMap[$planName] = $product->id;
                $newProducts++;
            }

            // Import accounts
            $alreadyImported = Service::whereNotNull('username')->pluck('username')->flip()->toArray();

            foreach ($data['accounts'] as $acct) {
                $username = $acct['username'];

                if (isset($alreadyImported[$username])) {
                    $skipped++;
                    continue;
                }

                // Find or create client
                $email  = ! empty($acct['email']) ? $acct['email'] : null;
                $user   = $email ? User::where('email', $email)->first() : null;
                $isNew  = false;

                if (! $user) {
                    $user = User::create([
                        'name'              => $username,
                        'email'             => $email ?? "{$username}@imported.local",
                        'password'          => bcrypt(Str::random(24)),
                        'email_verified_at' => now(),
                    ]);
                    $user->assignRole('client');
                    $isNew = true;
                    $newClients++;
                }

                // Resolve product
                $plan      = $acct['plan'] ?? '';
                $productId = $resolvedMap[$plan] ?? null;

                Service::create([
                    'user_id'           => $user->id,
                    'product_id'        => $productId,
                    'domain'            => $acct['domain'],
                    'username'          => $username,
                    'status'            => ($acct['suspended'] ?? false) ? 'suspended' : 'active',
                    'amount'            => $productId
                                            ? (Product::find($productId)?->price ?? 0)
                                            : 0,
                    'billing_cycle'     => $productId
                                            ? (Product::find($productId)?->billing_cycle ?? 'monthly')
                                            : 'monthly',
                    'registration_date' => now()->toDateString(),
                    'next_due_date'     => now()->addMonth()->toDateString(),
                    'server_hostname'   => $module->hostname,
                    'server_port'       => $module->port,
                    'module_data'       => [
                        'module_id'        => $module->id,
                        'remote_account_id' => $acct['remote_id'] ?? null,
                        'imported_at'      => now()->toISOString(),
                        'email_placeholder' => $isNew && ! $email,
                    ],
                ]);

                $module->increment('current_accounts');
                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'imported'     => $imported,
            'skipped'      => $skipped,
            'new_clients'  => $newClients,
            'new_products' => $newProducts,
        ]);
    }
}
