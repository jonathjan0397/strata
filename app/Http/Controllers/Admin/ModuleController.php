<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\ProvisionerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Modules/Index', [
            'modules' => Module::orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Modules/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'type'           => ['required', 'in:cpanel,plesk,directadmin,hestia,cwp,strata_panel,vestacp,cyberpanel,generic'],
            'hostname'       => ['required', 'string'],
            'port'           => ['required', 'integer', 'min:1', 'max:65535'],
            'local_hostname' => ['nullable', 'string'],
            'local_port'     => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username'       => ['required', 'string'],
            'api_token'      => ['nullable', 'string'],
            'password'       => ['nullable', 'string'],
            'ssl'            => ['boolean'],
            'active'         => ['boolean'],
            'max_accounts'   => ['nullable', 'integer', 'min:1'],
        ]);

        // Normalize optional string/int fields — empty strings from the form must not be
        // written to nullable integer/string DB columns (MySQL strict mode rejects '' for SMALLINT).
        $data['local_hostname'] = $data['local_hostname'] ?: null;
        $data['local_port']     = $data['local_port']     ?: null;
        $data['max_accounts']   = $data['max_accounts']   ?: null;

        $module = Module::create([
            ...$data,
            'api_token_enc' => isset($data['api_token']) ? encrypt($data['api_token']) : null,
            'password_enc'  => isset($data['password'])  ? encrypt($data['password'])  : null,
        ]);

        return redirect()->route('admin.modules.index')
            ->with('success', "Server \"{$module->name}\" added.");
    }

    public function edit(Module $module): Response
    {
        return Inertia::render('Admin/Modules/Form', ['module' => $module]);
    }

    public function update(Request $request, Module $module): RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'hostname'       => ['required', 'string'],
            'port'           => ['required', 'integer', 'min:1', 'max:65535'],
            'local_hostname' => ['nullable', 'string'],
            'local_port'     => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username'       => ['required', 'string'],
            'ssl'            => ['boolean'],
            'active'         => ['boolean'],
            'max_accounts'   => ['nullable', 'integer', 'min:1'],
        ]);

        $data['local_hostname'] = $data['local_hostname'] ?: null;
        $data['local_port']     = $data['local_port']     ?: null;
        $data['max_accounts']   = $data['max_accounts']   ?: null;

        $module->update($data);

        if ($request->filled('api_token')) {
            $module->update(['api_token_enc' => encrypt($request->api_token)]);
        }

        return back()->with('success', 'Server updated.');
    }

    public function destroy(Module $module): RedirectResponse
    {
        $module->delete();

        return redirect()->route('admin.modules.index')
            ->with('success', 'Server removed.');
    }

    /**
     * Return the list of packages/plans on a module — used by the product form.
     * Returns an empty array gracefully if the server is unreachable.
     */
    public function packages(Module $module): JsonResponse
    {
        if (! in_array($module->type, ProvisionerService::supportedTypes())) {
            return response()->json(['packages' => []]);
        }

        try {
            $driver   = ProvisionerService::forModule($module);
            $packages = $driver->listPackages();
        } catch (\Throwable $e) {
            return response()->json(['packages' => [], 'error' => $e->getMessage()]);
        }

        return response()->json(['packages' => $packages]);
    }
}
