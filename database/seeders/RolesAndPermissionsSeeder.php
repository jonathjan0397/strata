<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Granular staff permissions
        $staffPermissions = [
            'access.billing',
            'access.support',
            'access.technical',
            'access.clients',
            'access.reports',
        ];

        foreach ($staffPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Create roles
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client',      'guard_name' => 'web']);

        // Super-admin user is created by the installer wizard, not here.
    }
}
