<?php

namespace App\Licensing;

use App\Services\StrataLicense;

class License
{
    public static function hasFeature(string $key): bool
    {
        return StrataLicense::hasFeature($key);
    }

    public static function isActive(): bool
    {
        return StrataLicense::status() === 'active';
    }

    public static function messages(?string $type = null): array
    {
        return StrataLicense::messages($type);
    }

    public static function hasMessages(): bool
    {
        return StrataLicense::messages() !== [];
    }
}
