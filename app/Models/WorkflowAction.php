<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'type',
        'config',
        'delay_minutes',
        'sort_order',
    ];

    protected $casts = [
        'config'        => 'array',
        'delay_minutes' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
