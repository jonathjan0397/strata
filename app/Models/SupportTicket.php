<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'assigned_to', 'department_id', 'subject', 'status',
        'priority', 'department', 'last_reply_at', 'rating', 'rating_note',
        'first_replied_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at'    => 'datetime',
            'first_replied_at' => 'datetime',
            'closed_at'        => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportReply::class, 'ticket_id');
    }

    public function latestReply(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SupportReply::class, 'ticket_id')->latestOfMany();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'ticket_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
