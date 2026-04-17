<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Product;
use App\Services\ProvisionerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PackageSyncController extends Controller
{
    public function show(Module $module): Response
    {
        if (! in_array($module->type, ProvisionerService::supportedTypes())) {
            abort(422, 'This server type does not support package management.');
        }

        $panelPackages = [];
        $error = null;

        try {
            $driver = ProvisionerService::forModule($module);
            $panelPackages = $driver->listPackages();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        // Find existing Strata products already mapped to this server's packages
        $products = Product::where('module', $module->type)
            ->whereNotNull('module_config')
            ->get(['id', 'name', 'module_config', 'price', 'hidden']);

        // Build a lookup: plan name → product
        $productByPlan = [];
        foreach ($products as $p) {
            $plan = $p->module_config['plan'] ?? null;
            if ($plan) {
                $productByPlan[$plan] = ['id' => $p->id, 'name' => $p->name, 'price' => $p->price, 'hidden' => $p->hidden];
            }
        }

        // Annotate each panel package with its matching product (if any)
        $packages = array_map(fn ($pkg) => [
            ...$pkg,
            'product' => $productByPlan[$pkg['name']] ?? null,
        ], $panelPackages);

        return Inertia::render('Admin/Modules/PackageSync', [
            'module'   => $module,
            'packages' => $packages,
            'error'    => $error,
        ]);
    }

    /**
     * Import selected panel packages as Strata products.
     * Also handles creating new packages on the panel from scratch.
     */
    public function store(Request $request, Module $module): RedirectResponse
    {
        $data = $request->validate([
            'imports'                  => ['required', 'array', 'min:1'],
            'imports.*.name'           => ['required', 'string', 'max:255'],
            'imports.*.disk_mb'        => ['nullable', 'integer', 'min:0'],
            'imports.*.bandwidth_mb'   => ['nullable', 'integer', 'min:0'],
            'imports.*.create_on_panel' => ['boolean'],
        ]);

        if (! in_array($module->type, ProvisionerService::supportedTypes())) {
            return back()->with('error', 'This server type does not support package management.');
        }

        $driver = ProvisionerService::forModule($module);
        $created = 0;
        $errors  = [];

        foreach ($data['imports'] as $pkg) {
            $plan = $pkg['name'];

            // Optionally create the package on the panel first
            if (! empty($pkg['create_on_panel'])) {
                try {
                    if (! $driver->packageExists($plan)) {
                        $driver->createPackage($plan, [
                            'disk_mb'      => (int) ($pkg['disk_mb'] ?? 1024),
                            'bandwidth_mb' => (int) ($pkg['bandwidth_mb'] ?? 10240),
                        ]);
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Could not create \"{$plan}\" on panel: ".$e->getMessage();
                    continue;
                }
            }

            // Skip if a product already exists for this plan+server combo
            $exists = Product::where('module', $module->type)
                ->whereJsonContains('module_config->plan', $plan)
                ->whereJsonContains('module_config->module_id', $module->id)
                ->exists();

            if ($exists) {
                continue;
            }

            Product::create([
                'name'          => $plan,
                'type'          => 'shared',
                'price'         => 0,
                'setup_fee'     => 0,
                'billing_cycle' => 'monthly',
                'module'        => $module->type,
                'module_config' => [
                    'plan'        => $plan,
                    'module_id'   => $module->id,
                    'remote_package_id' => $pkg['id'] ?? null,
                    'package_slug' => $pkg['slug'] ?? null,
                    'disk_mb'     => (int) ($pkg['disk_mb'] ?? 0),
                    'bandwidth_mb' => (int) ($pkg['bandwidth_mb'] ?? 0),
                    'max_domains' => (int) ($pkg['max_domains'] ?? 0),
                    'max_email_accounts' => (int) ($pkg['max_email_accounts'] ?? 0),
                    'max_databases' => (int) ($pkg['max_databases'] ?? 0),
                    'max_ftp_accounts' => (int) ($pkg['max_ftp_accounts'] ?? 0),
                    'php_version' => $pkg['php_version'] ?? null,
                ],
                'autosetup'     => 'on_payment',
                'hidden'        => true,
                'taxable'       => true,
                'sort_order'    => 0,
            ]);

            $created++;
        }

        $msg = $created > 0
            ? "{$created} product(s) imported. Review and set pricing before making them visible."
            : 'No new products were created.';

        if ($errors) {
            $msg .= ' Errors: '.implode('; ', $errors);
        }

        return redirect()->route('admin.modules.packages.sync', $module->id)
            ->with('success', $msg);
    }

    /**
     * Create a new package directly on the panel (AJAX).
     */
    public function createOnPanel(Request $request, Module $module): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'disk_mb'      => ['required', 'integer', 'min:1'],
            'bandwidth_mb' => ['required', 'integer', 'min:1'],
        ]);

        if (! in_array($module->type, ProvisionerService::supportedTypes())) {
            return response()->json(['error' => 'Unsupported server type.'], 422);
        }

        try {
            $driver = ProvisionerService::forModule($module);

            if ($driver->packageExists($data['name'])) {
                return response()->json(['error' => "Package \"{$data['name']}\" already exists on this server."], 422);
            }

            $driver->createPackage($data['name'], [
                'disk_mb'      => $data['disk_mb'],
                'bandwidth_mb' => $data['bandwidth_mb'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true]);
    }
}
