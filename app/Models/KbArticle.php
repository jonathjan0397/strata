<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KbArticle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kb_category_id', 'author_id', 'title', 'slug',
        'body', 'published', 'views', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'kb_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    public function incrementViews(): void
    {
        $this->increment('views');
    }
}
