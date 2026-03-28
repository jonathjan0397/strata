<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceAddon extends Model
{
    protected $fillable = [
        'service_id', 'addon_id', 'status', 'amount', 'billing_cycle', 'next_due_date',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'next_due_date' => 'date',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
