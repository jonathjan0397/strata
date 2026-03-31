<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Get a system setting value by key.
     * Falls back to $default if the key is not found.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}
