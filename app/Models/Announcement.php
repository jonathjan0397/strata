<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'body', 'published', 'published_at'];

    protected function casts(): array
    {
        return [
            'published'    => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
