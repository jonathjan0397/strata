<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = ['name', 'rate', 'country', 'state', 'is_default', 'active'];

    protected function casts(): array
    {
        return [
            'rate'       => 'decimal:2',
            'is_default' => 'boolean',
            'active'     => 'boolean',
        ];
    }

    /**
     * Resolve the applicable tax rate for a user.
     * Priority: country+state match > country-only match > default rate.
     * Returns null (0%) if no match found or user is tax-exempt.
     */
    public static function resolveForUser(User $user): ?self
    {
        if ($user->tax_exempt) {
            return null;
        }

        $query = static::where('active', true);

        // Try country + state
        if ($user->country && $user->state) {
            $match = (clone $query)
                ->where('country', $user->country)
                ->where('state', $user->state)
                ->first();
            if ($match) return $match;
        }

        // Try country only
        if ($user->country) {
            $match = (clone $query)
                ->where('country', $user->country)
                ->whereNull('state')
                ->first();
            if ($match) return $match;
        }

        // Fall back to default rate
        return $query->where('is_default', true)->first();
    }
}
