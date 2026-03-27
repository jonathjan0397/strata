<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'status', 'subtotal', 'tax_rate', 'tax', 'total',
        'credit_applied', 'amount_due', 'date', 'due_date',
        'paid_at', 'payment_method', 'notes',
        'dunning_attempts', 'dunning_last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'       => 'decimal:2',
            'tax_rate'       => 'decimal:2',
            'tax'            => 'decimal:2',
            'total'          => 'decimal:2',
            'credit_applied' => 'decimal:2',
            'amount_due'     => 'decimal:2',
            'date'           => 'date',
            'due_date'       => 'date',
            'paid_at'                  => 'datetime',
            'dunning_last_attempt_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'unpaid' && $this->due_date->isPast();
    }
}
