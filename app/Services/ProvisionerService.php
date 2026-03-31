<?php

namespace App\Services;

use App\Contracts\ProvisionerDriver;
use App\Models\Module;
use App\Services\Provisioners\CpanelProvisioner;
use App\Services\Provisioners\DirectAdminProvisioner;
use App\Services\Provisioners\HestiaProvisioner;
use App\Services\Provisioners\PleskProvisioner;
use InvalidArgumentException;

class ProvisionerService
{
    private static array $drivers = [
        'cpanel' => CpanelProvisioner::class,
        'plesk' => PleskProvisioner::class,
        'directadmin' => DirectAdminProvisioner::class,
        'hestia' => HestiaProvisioner::class,
    ];

    /** Return a driver instance for the given module. */
    public static function forModule(Module $module): ProvisionerDriver
    {
        $type = $module->type;

        if (! isset(self::$drivers[$type])) {
            throw new InvalidArgumentException("No provisioner driver registered for type: [{$type}]");
        }

        return new (self::$drivers[$type])($module);
    }

    /** Find an active module of any supported type that has available capacity. */
    public static function findAvailableModule(?string $type = null): ?Module
    {
        $types = $type ? [$type] : array_keys(self::$drivers);

        return Module::whereIn('type', $types)
            ->where('active', true)
            ->get()
            ->first(fn (Module $m) => $m->hasCapacity());
    }

    /** Return module type slugs for all registered drivers. */
    public static function supportedTypes(): array
    {
        return array_keys(self::$drivers);
    }
}
