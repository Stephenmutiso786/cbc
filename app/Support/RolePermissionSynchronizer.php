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

        $permissionIds = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        foreach (RolePermissions::byRole() as $roleName => $permissions) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (! $role) {
                continue;
            }

            // Rebuild the pivots directly. A database created from an older
            // release can contain a pivot to a removed permission; Spatie
            // hydrates that stale pivot before syncPermissions() can replace
            // it and then throws a 500 on protected routes.
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            $rows = collect($permissions)
                ->map(fn (string $name) => $permissionIds->get($name))
                ->filter()
                ->map(fn ($permissionId) => [
                    'permission_id' => $permissionId,
                    'role_id' => $role->id,
                ])
                ->all();

            if ($rows) {
                DB::table('role_has_permissions')->insert($rows);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
