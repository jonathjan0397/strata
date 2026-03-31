<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:cpanel,plesk,directadmin,vestacp,cyberpanel,generic'],
            'hostname' => ['required', 'string'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string'],
            'api_token' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'ssl' => ['boolean'],
            'active' => ['boolean'],
            'max_accounts' => ['nullable', 'integer', 'min:1'],
        ]);

        $module = Module::create([
            ...$data,
            'api_token_enc' => isset($data['api_token']) ? encrypt($data['api_token']) : null,
            'password_enc' => isset($data['password']) ? encrypt($data['password']) : null,
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
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['required', 'string'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string'],
            'ssl' => ['boolean'],
            'active' => ['boolean'],
            'max_accounts' => ['nullable', 'integer', 'min:1'],
        ]);

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
}
