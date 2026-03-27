<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['slug', 'name', 'subject', 'body_html', 'body_plain', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->where('active', true)->first();
    }

    /**
     * Replace {{variable}} placeholders with values.
     */
    public function render(string $field, array $vars): string
    {
        $content = $this->{$field} ?? '';

        foreach ($vars as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }

        return $content;
    }
}
