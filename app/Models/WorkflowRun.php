<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'trigger',
        'target_type',
        'target_id',
        'status',
        'log',
        'ran_at',
    ];

    protected $casts = [
        'log' => 'array',
        'ran_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
