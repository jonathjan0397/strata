<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    /**
     * Validate a promo code and return the discount details.
     * Called via AJAX from the checkout page.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code'       => ['required', 'string'],
            'product_id' => ['required', 'exists:products,id'],
            'price'      => ['required', 'numeric', 'min:0'],
            'setup_fee'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $price    = (float) $request->price;
        $setupFee = (float) ($request->setup_fee ?? 0);
        $user     = $request->user();

        $promo = PromoCode::where('code', strtoupper($request->code))->first();

        if (! $promo || ! $promo->isValid($user)) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired promo code.'], 422);
        }

        // Product restriction
        if ($promo->product_id !== null && (int) $promo->product_id !== (int) $request->product_id) {
            return response()->json(['valid' => false, 'message' => 'This code is not valid for the selected product.'], 422);
        }

        $discount = $promo->calculateDiscount($price, $setupFee);

        $label = match ($promo->type) {
            'percent'    => "{$promo->value}% off",
            'free_setup' => 'Setup fee waived',
            default      => '$' . number_format((float) $promo->value, 2) . ' off',
        };

        return response()->json([
            'valid'    => true,
            'code'     => strtoupper($promo->code),
            'type'     => $promo->type,
            'value'    => $promo->value,
            'discount' => $discount,
            'label'    => $label,
        ]);
    }
}
