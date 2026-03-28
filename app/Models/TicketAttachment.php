<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    protected $fillable = [
        'ticket_id', 'reply_id', 'user_id',
        'filename', 'path', 'size', 'mime_type',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(SupportReply::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function humanSize(): string
    {
        if ($this->size < 1024) return "{$this->size} B";
        if ($this->size < 1048576) return round($this->size / 1024, 1) . ' KB';
        return round($this->size / 1048576, 1) . ' MB';
    }

    public function downloadUrl(): string
    {
        return route('support.attachments.download', $this->id);
    }
}
