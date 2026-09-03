<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'view results', 'guard_name' => 'web']);
        foreach ([
            'hod', 'class-teacher', 'teacher', 'pre-primary-teacher',
            'lower-primary-teacher', 'upper-primary-teacher', 'junior-secondary-teacher',
        ] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'view results')->where('guard_name', 'web')->first();
        if (! $permission) return;
        Role::whereIn('name', [
            'hod', 'class-teacher', 'teacher', 'pre-primary-teacher',
            'lower-primary-teacher', 'upper-primary-teacher', 'junior-secondary-teacher',
        ])->get()->each(fn (Role $role) => $role->revokePermissionTo($permission));
    }
};
