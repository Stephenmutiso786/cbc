<?php

use App\Support\RolePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (RolePermissions::all() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (RolePermissions::byRole() as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissionNames);
        }

        // Staff accounts have one operational role. Remove stale elevated roles
        // from older assignments when a teaching role is already present.
        $teachingRoles = Role::whereIn('name', [
            'hod', 'class-teacher', 'teacher', 'pre-primary-teacher',
            'lower-primary-teacher', 'upper-primary-teacher', 'junior-secondary-teacher',
        ])->pluck('id', 'name');
        if (! Schema::hasTable('staff_members')) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            return;
        }
        $staffUserIds = DB::table('staff_members')->whereNotNull('user_id')->pluck('user_id');
        foreach ($staffUserIds as $userId) {
            $teachingRoleId = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')->where('model_id', $userId)
                ->whereIn('role_id', $teachingRoles->values())->value('role_id');
            if ($teachingRoleId) {
                DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')
                    ->where('model_id', $userId)->where('role_id', '<>', $teachingRoleId)->delete();
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Do not restore insecure role assignments on rollback.
    }
};
