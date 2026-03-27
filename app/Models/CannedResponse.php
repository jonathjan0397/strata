<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CannedResponse extends Model
{
    protected $fillable = ['department_id', 'title', 'body'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
