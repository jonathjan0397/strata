<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MailboxPipe extends Model
{
    protected $fillable = [
        'name', 'email_address', 'pipe_token',
        'department_id', 'auto_assign_to', 'default_priority',
        'create_client_if_not_exists', 'strip_signature',
        'auto_reply_enabled', 'auto_reply_subject', 'auto_reply_body',
        'reject_unknown_senders', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'create_client_if_not_exists' => 'boolean',
            'strip_signature'             => 'boolean',
            'auto_reply_enabled'          => 'boolean',
            'reject_unknown_senders'      => 'boolean',
            'is_active'                   => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auto_assign_to');
    }

    /** Generate a cryptographically random pipe token. */
    public static function generateToken(): string
    {
        return Str::random(40);
    }
}
