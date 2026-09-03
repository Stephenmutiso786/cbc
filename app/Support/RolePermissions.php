<?php

namespace App\Support;

final class RolePermissions
{
    public static function all(): array
    {
        return ['view students','create students','edit students','delete students','view assessments','create assessments','edit assessments','delete assessments','view report cards','generate report cards','view fees','manage fees','record payments','view finance reports','export finance','view inventory','manage inventory','issue items','receive items','view staff','manage staff','manage payroll','view timetable','manage timetable','view notes','upload notes','manage curriculum','publish notes','view exams','manage exams','enter marks','view results','review marks','publish results','view attendance','mark attendance','send notifications','view notifications','sync kemis','export kemis','view analytics','export reports','manage system settings','manage users','manage roles','manage promotions'];
    }

    public static function byRole(): array
    {
        $all = self::all();
        return [
            'super-admin' => $all,
            'admin' => $all,
            'principal' => array_diff($all, ['manage system settings', 'manage roles']),
            'headteacher' => array_diff($all, ['manage system settings', 'manage roles']),
            'deputy-headteacher' => ['view students','view assessments','view timetable','manage timetable','view notes','view exams','review marks','manage promotions','view analytics','view attendance','mark attendance'],
            'deputy' => ['view students','view assessments','view timetable','manage timetable','view notes','view exams','review marks','manage promotions','view analytics','view attendance','mark attendance'],
            'hod' => ['view students','view assessments','create assessments','edit assessments','view notes','upload notes','publish notes','manage curriculum','view timetable','view exams','manage exams','enter marks','view results','review marks','view report cards'],
            'class-teacher' => ['view students','view assessments','create assessments','edit assessments','view notes','upload notes','view timetable','enter marks','view attendance','mark attendance','view results','view report cards'],
            'teacher' => self::teacherPermissions(),
            'pre-primary-teacher' => self::teacherPermissions(),
            'lower-primary-teacher' => self::teacherPermissions(),
            'upper-primary-teacher' => self::teacherPermissions(),
            'junior-secondary-teacher' => array_merge(self::teacherPermissions(), ['view exams']),
            'bursar' => ['view students','view fees','manage fees','record payments','view finance reports','export finance','view inventory','manage inventory','issue items','receive items'],
            'accountant' => ['view fees','record payments','view finance reports','export finance'],
            'librarian' => ['view inventory','manage inventory','issue items','receive items'],
            'storekeeper' => ['view inventory','manage inventory','issue items','receive items'],
            'parent' => ['view report cards','view notes','view fees'],
            'learner' => ['view notes','view timetable'],
        ];
    }

    private static function teacherPermissions(): array
    {
        return ['view students','view assessments','create assessments','view notes','upload notes','view timetable','enter marks','view results','send notifications'];
    }
}
