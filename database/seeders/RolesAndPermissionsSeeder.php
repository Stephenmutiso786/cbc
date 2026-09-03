<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view students','create students','edit students','delete students',
            'view assessments','create assessments','edit assessments','delete assessments',
            'view report cards','generate report cards',
            'view fees','manage fees','record payments','view finance reports','export finance',
            'view inventory','manage inventory','issue items','receive items',
            'view staff','manage staff','manage payroll',
            'view timetable','manage timetable',
            'view notes','upload notes','manage curriculum','publish notes',
            'view exams','manage exams','enter marks','view results','review marks','publish results',
            'view attendance','mark attendance',
            'send notifications','view notifications',
            'sync kemis','export kemis',
            'view analytics','export reports',
            'manage system settings','manage users','manage roles','manage promotions',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'admin'            => $permissions,
            'super-admin'      => $permissions,
            'principal'        => array_diff($permissions, ['manage system settings','manage roles']),
            'headteacher'      => array_diff($permissions, ['manage system settings','manage roles']),
            'deputy-headteacher' => ['view students','view assessments','view timetable','manage timetable',
                                   'view notes','view exams','review marks','manage promotions','view analytics','view attendance','mark attendance'],
            'deputy'           => ['view students','view assessments','view timetable','manage timetable',
                                   'view notes','view exams','review marks','manage promotions','view analytics','view attendance','mark attendance'],
            'hod'              => ['view students','view assessments','create assessments','edit assessments',
                                   'view notes','upload notes','publish notes','manage curriculum',
                                   'view exams','manage exams','enter marks','view results','review marks'],
            'class-teacher'    => ['view students','view assessments','create assessments','edit assessments',
                                   'view notes','upload notes','view timetable','enter marks',
                                   'view attendance','mark attendance','view results','view report cards'],
            'teacher'          => ['view students','view assessments','create assessments',
                                   'view notes','upload notes','view timetable','enter marks','view results','send notifications'],
            'pre-primary-teacher' => ['view students','view assessments','create assessments',
                                      'view notes','upload notes','view timetable','enter marks','view results','send notifications'],
            'lower-primary-teacher' => ['view students','view assessments','create assessments',
                                        'view notes','upload notes','view timetable','enter marks','view results','send notifications'],
            'upper-primary-teacher' => ['view students','view assessments','create assessments',
                                        'view notes','upload notes','view timetable','enter marks','view results','send notifications'],
            'junior-secondary-teacher' => ['view students','view assessments','create assessments',
                                           'view notes','upload notes','view timetable','view exams','enter marks','view results','send notifications'],
            'bursar'           => ['view students','view fees','manage fees','record payments',
                                   'view finance reports','export finance','view inventory','manage inventory',
                                   'issue items','receive items'],
            'librarian'        => ['view inventory','manage inventory','issue items','receive items'],
            'storekeeper'      => ['view inventory','manage inventory','issue items','receive items'],
            'parent'           => ['view report cards','view notes','view fees'],
            'learner'          => ['view notes','view timetable'],
        ];

        foreach ($rolePermissions as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePerms);
        }

        $this->command->info('✅ Roles & Permissions seeded');
    }
}
