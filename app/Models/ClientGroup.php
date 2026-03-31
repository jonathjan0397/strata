<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientGroup extends Model
{
    protected $fillable = ['name', 'description', 'discount_type', 'discount_value'];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Calculate the discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        return match ($this->discount_type) {
            'percent' => round($subtotal * ($this->discount_value / 100), 2),
            'fixed' => min((float) $this->discount_value, $subtotal),
            default => 0.0,
        };
    }
}
