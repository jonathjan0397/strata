<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $fillable = [
        'name',
        'trigger',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function conditions(): HasMany
    {
        return $this->hasMany(WorkflowCondition::class)->orderBy('sort_order');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class)->orderBy('sort_order');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class)->latest('ran_at');
    }
}
