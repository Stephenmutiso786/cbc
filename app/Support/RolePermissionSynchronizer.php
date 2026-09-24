<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use App\Models\RolePermissionOverride;
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
            $override = RolePermissionOverride::query()->where('role_id', $role->id)->value('permission_ids');
            $effectivePermissions = is_array($override) ? $override : $permissions;

            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            // Overrides persist permission primary keys, while the canonical
            // matrix uses permission names. `$permissionIds` is keyed by
            // name, so reversing it for a numeric override returns the name
            // and attempts to insert text into permission_id on PostgreSQL.
            // Keep a numeric id as an id after confirming it is in the
            // current catalog; resolve a name through the name-to-id map.
            $knownPermissionIds = $permissionIds->flip();
            $rows = collect($effectivePermissions)
                ->map(fn ($permission) => is_numeric($permission)
                    ? ($knownPermissionIds->has((int) $permission) ? (int) $permission : null)
                    : $permissionIds->get($permission))
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
