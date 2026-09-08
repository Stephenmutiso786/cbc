<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'submit support tickets',
            'guard_name' => 'web',
        ]);

        foreach (['deputy-headteacher', 'deputy', 'hod', 'class-teacher'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'submit support tickets')->where('guard_name', 'web')->first();
        if (! $permission) {
            return;
        }

        foreach (['deputy-headteacher', 'deputy', 'hod', 'class-teacher'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->revokePermissionTo($permission);
        }
    }
};
