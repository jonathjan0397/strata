<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client',      'guard_name' => 'web']);

        // Create default super-admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@strata.local'],
            [
                'name'              => 'Super Admin',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('super-admin');
    }
}
