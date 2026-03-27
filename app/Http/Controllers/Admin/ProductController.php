<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'type'          => ['required', 'in:shared,reseller,vps,dedicated,domain,ssl,other'],
            'price'         => ['required', 'numeric', 'min:0'],
            'setup_fee'     => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semi_annual,annual,biennial,triennial,one_time'],
            'module'        => ['nullable', 'string'],
            'module_config' => ['nullable', 'array'],
            'hidden'        => ['boolean'],
            'taxable'       => ['boolean'],
            'sort_order'    => ['integer'],
        ]);

        $product = Product::create($data);

        return redirect()->route('admin.products.index')
            ->with('success', "Product \"{$product->name}\" created.");
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', ['product' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'type'          => ['required', 'in:shared,reseller,vps,dedicated,domain,ssl,other'],
            'price'         => ['required', 'numeric', 'min:0'],
            'setup_fee'     => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semi_annual,annual,biennial,triennial,one_time'],
            'module'        => ['nullable', 'string'],
            'module_config' => ['nullable', 'array'],
            'hidden'        => ['boolean'],
            'taxable'       => ['boolean'],
            'sort_order'    => ['integer'],
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
