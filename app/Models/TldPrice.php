<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TldPrice extends Model
{
    protected $table = 'tld_pricing';

    protected $fillable = [
        'tld',
        'register_cost',
        'renew_cost',
        'transfer_cost',
        'markup_type',
        'markup_value',
        'currency',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'register_cost' => 'float',
        'renew_cost'    => 'float',
        'transfer_cost' => 'float',
        'markup_value'  => 'float',
        'is_active'     => 'boolean',
        'last_synced_at'=> 'datetime',
    ];

    /** Final register price after markup. */
    public function getRegisterPriceAttribute(): ?float
    {
        return $this->applyMarkup($this->register_cost);
    }

    /** Final renew price after markup. */
    public function getRenewPriceAttribute(): ?float
    {
        return $this->applyMarkup($this->renew_cost);
    }

    /** Final transfer price after markup. */
    public function getTransferPriceAttribute(): ?float
    {
        return $this->applyMarkup($this->transfer_cost);
    }

    private function applyMarkup(?float $cost): ?float
    {
        if ($cost === null) {
            return null;
        }

        if ($this->markup_type === 'fixed') {
            return round($cost + $this->markup_value, 2);
        }

        // percent
        return round($cost * (1 + $this->markup_value / 100), 2);
    }

    /** Find the pricing row for a given full domain name (extracts TLD). */
    public static function forDomain(string $domain): ?static
    {
        $tld = '.' . implode('.', array_slice(explode('.', $domain), 1));
        return static::where('tld', $tld)->where('is_active', true)->first();
    }
}
