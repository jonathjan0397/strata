<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'product_id', 'domain', 'status', 'amount', 'billing_cycle',
        'registration_date', 'next_due_date', 'termination_date',
        'username', 'password_enc', 'server_hostname', 'server_port',
        'module_data', 'notes', 'cancellation_reason', 'cancellation_requested_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'                      => 'decimal:2',
            'registration_date'           => 'date',
            'next_due_date'               => 'date',
            'termination_date'            => 'date',
            'cancellation_requested_at'   => 'datetime',
            'module_data'                 => 'array',
        ];
    }

    protected $hidden = ['password_enc'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain', 'name');
    }
}
