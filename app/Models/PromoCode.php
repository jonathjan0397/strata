<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'type',             // 'percent' | 'fixed' | 'free_setup'
        'value',            // percentage or fixed amount (ignored for free_setup)
        'product_id',       // null = applies to any product
        'max_uses',         // null = unlimited
        'uses_count',
        'applies_once',     // true = each client may use once
        'new_clients_only', // true = only clients with no prior active services
        'recurring_cycles', // null/0=first invoice only; n>0=n invoices; -1=all
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'applies_once' => 'boolean',
            'new_clients_only' => 'boolean',
            'recurring_cycles' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if this code is currently usable.
     * Pass $user to also enforce per-client and new-client restrictions.
     */
    public function isValid(?User $user = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        if ($user) {
            // Per-client once-only
            if ($this->applies_once) {
                $alreadyUsed = Order::where('user_id', $user->id)
                    ->where('promo_code', $this->code)
                    ->exists();

                if ($alreadyUsed) {
                    return false;
                }
            }

            // New clients only — no prior active or suspended services
            if ($this->new_clients_only) {
                $hasServices = Service::where('user_id', $user->id)
                    ->whereIn('status', ['active', 'suspended'])
                    ->exists();

                if ($hasServices) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Calculate the discount amount.
     *
     * @param  float  $price  The recurring product price (excl. setup fee)
     * @param  float  $setupFee  The one-time setup fee
     */
    public function calculateDiscount(float $price, float $setupFee = 0): float
    {
        $subtotal = $price + $setupFee;

        return match ($this->type) {
            'percent' => round($subtotal * ((float) $this->value / 100), 2),
            'free_setup' => $setupFee,
            default => min((float) $this->value, $subtotal), // 'fixed'
        };
    }

    /** Scope: active, started, and not expired. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
