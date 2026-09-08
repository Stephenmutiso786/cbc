<?php

namespace App\Support;

final class RolePermissions
{
    public static function all(): array
    {
        return ['view students','create students','edit students','delete students','view assessments','create assessments','edit assessments','delete assessments','view report cards','generate report cards','view fees','manage fees','record payments','view finance reports','export finance','view inventory','manage inventory','issue items','receive items','view staff','manage staff','manage payroll','view timetable','manage timetable','view notes','upload notes','manage curriculum','publish notes','view exams','manage exams','enter marks','view results','review marks','publish results','view attendance','mark attendance','send notifications','view notifications','sync kemis','export kemis','view analytics','export reports','manage system settings','manage users','manage roles','manage promotions','submit support tickets','manage support tickets','run diagnostics','manage legal policies'];
    }

    public static function byRole(): array
    {
        $all = self::all();
        $superAdminOnly = ['manage roles', 'run diagnostics', 'manage legal policies'];
        return [
            'super-admin' => $all,
            'school-admin' => array_diff($all, $superAdminOnly),
            'principal' => array_diff($all, array_merge(['manage system settings'], $superAdminOnly)),
            'headteacher' => array_diff($all, array_merge(['manage system settings'], $superAdminOnly)),
            'deputy-headteacher' => ['view students','view assessments','view timetable','manage timetable','view notes','view exams','review marks','manage promotions','view analytics','view attendance','mark attendance','submit support tickets'],
            'deputy' => ['view students','view assessments','view timetable','manage timetable','view notes','view exams','review marks','manage promotions','view analytics','view attendance','mark attendance','submit support tickets'],
            'hod' => ['view students','view assessments','create assessments','edit assessments','view notes','upload notes','publish notes','manage curriculum','view timetable','view exams','manage exams','enter marks','view results','review marks','view report cards','submit support tickets'],
            'class-teacher' => ['view students','view assessments','create assessments','edit assessments','view notes','upload notes','view timetable','enter marks','view attendance','mark attendance','view results','view report cards','submit support tickets'],
            'teacher' => self::teacherPermissions(),
            'pre-primary-teacher' => self::teacherPermissions(),
            'lower-primary-teacher' => self::teacherPermissions(),
            'upper-primary-teacher' => self::teacherPermissions(),
            'junior-secondary-teacher' => array_merge(self::teacherPermissions(), ['view exams']),
            'bursar' => ['view students','view fees','manage fees','record payments','view finance reports','export finance','view inventory','manage inventory','issue items','receive items','submit support tickets'],
            'accountant' => ['view fees','record payments','view finance reports','export finance','submit support tickets'],
            'librarian' => ['view inventory','manage inventory','issue items','receive items'],
            'storekeeper' => ['view inventory','manage inventory','issue items','receive items'],
            'parent' => ['view report cards','view notes','view fees','submit support tickets'],
            'learner' => ['view notes','view timetable','submit support tickets'],
        ];
    }

    private static function teacherPermissions(): array
    {
        return ['view students','view assessments','create assessments','view notes','upload notes','view timetable','enter marks','view results','send notifications','submit support tickets'];
    }
}
