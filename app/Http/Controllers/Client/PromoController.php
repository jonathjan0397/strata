<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
            'subtotal'   => ['required', 'numeric', 'min:0'],
        ]);

        $promo = PromoCode::where('code', strtoupper($request->code))->first();

        if (! $promo || ! $promo->isValid()) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired promo code.'], 422);
        }

        // Check product restriction
        if ($promo->product_id !== null && (int) $promo->product_id !== (int) $request->product_id) {
            return response()->json(['valid' => false, 'message' => 'This code is not valid for the selected product.'], 422);
        }

        // Check per-client once-only
        if ($promo->applies_once) {
            $alreadyUsed = Order::where('user_id', $request->user()->id)
                ->where('promo_code', strtoupper($request->code))
                ->exists();

            if ($alreadyUsed) {
                return response()->json(['valid' => false, 'message' => 'You have already used this promo code.'], 422);
            }
        }

        $discount = $promo->calculateDiscount((float) $request->subtotal);

        return response()->json([
            'valid'    => true,
            'code'     => strtoupper($promo->code),
            'type'     => $promo->type,
            'value'    => $promo->value,
            'discount' => $discount,
            'label'    => $promo->type === 'percent'
                ? "{$promo->value}% off"
                : '$'.number_format((float) $promo->value, 2).' off',
        ]);
    }
}
