<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class);
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(KbArticle::class)->where('published', true);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('sort_order')->orderBy('name');
    }
}
