<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Get a setting value by key. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::allKeyed();
        return $settings[$key] ?? $default;
    }

    /** Set (upsert) a single setting. Clears cache. */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app_settings');
    }

    /** Batch upsert an array of key => value pairs. Clears cache. */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('app_settings');
    }

    /**
     * Return all settings as a key => value array.
     * Cached for 60 minutes; bust on write.
     */
    public static function allKeyed(): array
    {
        try {
            return Cache::remember('app_settings', 3600, function () {
                return static::pluck('value', 'key')->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }
}
