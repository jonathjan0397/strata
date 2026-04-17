<?php

namespace App\Licensing;

class Features
{
    /**
     * Return every premium feature key understood by this application.
     *
     * This is intentionally empty today. Add stable snake_case constants here
     * as premium functionality is introduced in the future.
     */
    public static function all(): array
    {
        return [];
    }

    /**
     * Return the premium features currently enabled on this installation.
     */
    public static function enabledKeys(): array
    {
        return [];
    }
}
