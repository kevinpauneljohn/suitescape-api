<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::create(['name' => 'create listing']);
        Permission::create(['name' => 'edit listing']);
        Permission::create(['name' => 'delete listing']);
        Permission::create(['name' => 'update setting']);

        // create roles and assign created permissions
        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo(Permission::all());

        $host = Role::create(['name' => 'host']);
        $host->givePermissionTo(['create listing', 'edit listing', 'delete listing']);

        Role::create(['name' => 'guest']);
    }
}
