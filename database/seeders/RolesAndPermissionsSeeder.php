<?php

namespace Database\Seeders;

use App\Support\RolePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (RolePermissions::all() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (RolePermissions::byRole() as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissionNames);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command->info('Roles & permissions reconciled.');
    }
}
