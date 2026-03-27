<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'type',         // 'percent' | 'fixed'
        'value',        // percentage (e.g. 20 = 20%) or fixed amount (e.g. 10.00)
        'product_id',   // null = applies to any product
        'max_uses',     // null = unlimited
        'uses_count',
        'applies_once', // true = each client can use once only
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value'        => 'decimal:2',
            'max_uses'     => 'integer',
            'uses_count'   => 'integer',
            'applies_once' => 'boolean',
            'is_active'    => 'boolean',
            'expires_at'   => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Check if this code is currently usable. */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->type === 'percent') {
            return round($subtotal * ((float) $this->value / 100), 2);
        }

        // Fixed — cannot exceed subtotal
        return min((float) $this->value, $subtotal);
    }

    /** Scope: active and not expired. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
