<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxRateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/TaxRates/Index', [
            'rates' => TaxRate::orderBy('country')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'rate'       => ['required', 'numeric', 'min:0', 'max:100'],
            'country'    => ['nullable', 'string', 'size:2'],
            'state'      => ['nullable', 'string', 'max:10'],
            'is_default' => ['boolean'],
            'active'     => ['boolean'],
        ]);

        // Only one default at a time
        if ($request->boolean('is_default')) {
            TaxRate::where('is_default', true)->update(['is_default' => false]);
        }

        TaxRate::create($request->only('name', 'rate', 'country', 'state', 'is_default', 'active'));

        return back()->with('success', 'Tax rate created.');
    }

    public function update(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'rate'       => ['required', 'numeric', 'min:0', 'max:100'],
            'country'    => ['nullable', 'string', 'size:2'],
            'state'      => ['nullable', 'string', 'max:10'],
            'is_default' => ['boolean'],
            'active'     => ['boolean'],
        ]);

        if ($request->boolean('is_default')) {
            TaxRate::where('id', '!=', $taxRate->id)->where('is_default', true)->update(['is_default' => false]);
        }

        $taxRate->update($request->only('name', 'rate', 'country', 'state', 'is_default', 'active'));

        return back()->with('success', 'Tax rate updated.');
    }

    public function destroy(TaxRate $taxRate): RedirectResponse
    {
        $taxRate->delete();

        return back()->with('success', 'Tax rate deleted.');
    }
}
