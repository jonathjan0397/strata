<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddonController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Addons/Index', [
            'addons' => Addon::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Addons/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'setup_fee' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semi_annual,annual,biennial,triennial,one_time'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $addon = Addon::create($data);

        return redirect()->route('admin.addons.index')
            ->with('success', "Addon \"{$addon->name}\" created.");
    }

    public function edit(Addon $addon): Response
    {
        return Inertia::render('Admin/Addons/Form', ['addon' => $addon]);
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'setup_fee' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semi_annual,annual,biennial,triennial,one_time'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $addon->update($data);

        return back()->with('success', 'Addon updated.');
    }

    public function destroy(Addon $addon): RedirectResponse
    {
        $addon->delete();

        return redirect()->route('admin.addons.index')
            ->with('success', 'Addon deleted.');
    }
}
