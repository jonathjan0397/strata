<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    protected $fillable = [
        'user_id', 'code', 'status', 'commission_type', 'commission_value',
        'balance', 'total_earned', 'payout_threshold', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:2',
            'balance'          => 'decimal:2',
            'total_earned'     => 'decimal:2',
            'payout_threshold' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function calculateCommission(float $orderTotal): float
    {
        if ($this->commission_type === 'percent') {
            return round($orderTotal * ((float) $this->commission_value / 100), 2);
        }

        return min((float) $this->commission_value, $orderTotal);
    }
}
