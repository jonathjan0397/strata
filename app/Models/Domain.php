<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'service_id', 'name', 'registrar', 'status',
        'registered_at', 'expires_at', 'auto_renew', 'locked', 'privacy',
        'nameserver_1', 'nameserver_2', 'nameserver_3', 'nameserver_4',
        'registrar_data',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'date',
            'expires_at' => 'date',
            'auto_renew' => 'boolean',
            'locked' => 'boolean',
            'privacy' => 'boolean',
            'registrar_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expires_at && $this->expires_at->diffInDays(now()) <= $days;
    }
}
