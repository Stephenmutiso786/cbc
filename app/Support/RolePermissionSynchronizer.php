<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSynchronizer
{
    public function synchronize(): void
    {
        foreach (RolePermissions::all() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RolePermissions::byRole() as $roleName => $permissions) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (! $role) {
                continue;
            }

            // Remove obsolete pivot ids before Spatie resolves them during sync.
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
