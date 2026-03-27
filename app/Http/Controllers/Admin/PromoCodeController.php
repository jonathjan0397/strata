<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PromoCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromoCodeController extends Controller
{
    public function index(): Response
    {
        $codes = PromoCode::with('product')
            ->latest()
            ->paginate(25);

        return Inertia::render('Admin/PromoCodes/Index', [
            'codes' => $codes,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PromoCodes/Edit', [
            'code'     => null,
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        PromoCode::create($data);

        return redirect()->route('admin.promo-codes.index')
            ->with('success', 'Promo code created.');
    }

    public function edit(PromoCode $promoCode): Response
    {
        return Inertia::render('Admin/PromoCodes/Edit', [
            'code'     => $promoCode,
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $data = $request->validate($this->rules($promoCode->id));
        $promoCode->update($data);

        return redirect()->route('admin.promo-codes.index')
            ->with('success', 'Promo code updated.');
    }

    public function destroy(PromoCode $promoCode): RedirectResponse
    {
        $promoCode->delete();

        return back()->with('success', 'Promo code deleted.');
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'code'         => ['required', 'string', 'max:64', 'unique:promo_codes,code'.($ignoreId ? ",{$ignoreId}" : '')],
            'type'         => ['required', 'in:percent,fixed'],
            'value'        => ['required', 'numeric', 'min:0.01'],
            'product_id'   => ['nullable', 'exists:products,id'],
            'max_uses'     => ['nullable', 'integer', 'min:1'],
            'applies_once' => ['boolean'],
            'is_active'    => ['boolean'],
            'expires_at'   => ['nullable', 'date'],
        ];
    }
}
