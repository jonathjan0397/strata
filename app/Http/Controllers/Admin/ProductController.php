<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Products/Index', [
            'products' => Product::withCount('services')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function modules(): array
    {
        return Module::where('active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'hostname', 'port', 'current_accounts', 'max_accounts'])
            ->toArray();
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'modules' => $this->modules(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:shared,reseller,vps,dedicated,domain,ssl,other'],
            'price' => ['required', 'numeric', 'min:0'],
            'setup_fee' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semi_annual,annual,biennial,triennial,one_time'],
            'module' => ['nullable', 'string'],
            'autosetup' => ['nullable', 'in:on_order,on_payment,manual,never'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'module_config' => ['nullable', 'array'],
            'configurable_options' => ['nullable', 'array'],
            'configurable_options.*.name' => ['required_with:configurable_options', 'string', 'max:100'],
            'configurable_options.*.choices' => ['required_with:configurable_options', 'array', 'min:1'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'hidden' => ['boolean'],
            'taxable' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $product = Product::create($data);

        return redirect()->route('admin.products.index')
            ->with('success', "Product \"{$product->name}\" created.");
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => $product,
            'modules' => $this->modules(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:shared,reseller,vps,dedicated,domain,ssl,other'],
            'price' => ['required', 'numeric', 'min:0'],
            'setup_fee' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semi_annual,annual,biennial,triennial,one_time'],
            'module' => ['nullable', 'string'],
            'autosetup' => ['nullable', 'in:on_order,on_payment,manual,never'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'module_config' => ['nullable', 'array'],
            'configurable_options' => ['nullable', 'array'],
            'configurable_options.*.name' => ['required_with:configurable_options', 'string', 'max:100'],
            'configurable_options.*.choices' => ['required_with:configurable_options', 'array', 'min:1'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'hidden' => ['boolean'],
            'taxable' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $product->update($data);

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted.');
    }
}
