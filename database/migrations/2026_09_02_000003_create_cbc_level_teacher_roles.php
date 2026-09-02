<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        $teacherPermissions = [
            'view students', 'view assessments', 'create assessments',
            'view notes', 'upload notes', 'view timetable', 'enter marks',
        ];

        $levelRoles = [
            'pre-primary-teacher' => $teacherPermissions,
            'lower-primary-teacher' => $teacherPermissions,
            'upper-primary-teacher' => $teacherPermissions,
            'junior-secondary-teacher' => [...$teacherPermissions, 'view exams'],
        ];

        foreach ($levelRoles as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $permissions = collect($permissionNames)->map(
                fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
            );
            $role->syncPermissions($permissions);
        }
    }

    public function down(): void
    {
        foreach ([
            'pre-primary-teacher', 'lower-primary-teacher',
            'upper-primary-teacher', 'junior-secondary-teacher',
        ] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->delete();
        }
    }
};
