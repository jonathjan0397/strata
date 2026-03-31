<?php

namespace App\Services;

use App\Contracts\RegistrarDriver;
use App\Services\Registrars\EnomDriver;
use App\Services\Registrars\HexonetDriver;
use App\Services\Registrars\NamecheapDriver;
use App\Services\Registrars\OpenSRSDriver;
use InvalidArgumentException;

class DomainRegistrarService
{
    private static array $drivers = [
        'namecheap' => NamecheapDriver::class,
        'enom' => EnomDriver::class,
        'opensrs' => OpenSRSDriver::class,
        'hexonet' => HexonetDriver::class,
    ];

    public static function driver(?string $name = null): RegistrarDriver
    {
        $name ??= config('registrars.default', 'namecheap');

        if (! isset(self::$drivers[$name])) {
            throw new InvalidArgumentException("Unknown registrar driver: [{$name}]");
        }

        return app(self::$drivers[$name]);
    }

    /** Return slugs of all registered drivers. */
    public static function available(): array
    {
        return array_keys(self::$drivers);
    }

    /**
     * Check availability across the default registrar.
     *
     * @return array{available: bool, registrar: string}
     */
    public static function checkAvailability(string $domain): array
    {
        $driver = self::driver();
        $result = $driver->checkAvailability($domain);

        return array_merge($result, ['registrar' => $driver->slug()]);
    }
}
