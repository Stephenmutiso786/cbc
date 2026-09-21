<?php

namespace App\Support;

use App\Models\User;

final class PortalRedirector
{
    public static function destinationFor(User $user): string
    {
        if ($user->hasRole('super-admin')) return route('admin.platform-dashboard.index');
        if ($user->hasRole('it-team')) return route('it.support.index');
        if ($user->hasAnyRole(['school-admin', 'headteacher', 'principal', 'deputy-headteacher', 'deputy'])) return route('admin.dashboard');
        if ($user->hasAnyRole(['hod', 'teacher', 'class-teacher', 'pre-primary-teacher', 'lower-primary-teacher', 'upper-primary-teacher', 'junior-secondary-teacher'])) return route('teacher.dashboard');
        if ($user->hasRole('parent')) return route('parent.dashboard');
        if ($user->hasAnyRole(['bursar', 'accountant'])) return route('finance.dashboard');
        if ($user->hasRole('learner')) return route('student.dashboard');

        if ($user->canAny(['manage system settings', 'manage roles', 'manage staff', 'manage curriculum', 'manage fees'])) return route('admin.dashboard');
        if ($user->canAny(['enter marks', 'view assessments', 'view results', 'view notes', 'view timetable'])) return route('teacher.dashboard');
        if ($user->canAny(['view fees', 'record payments', 'view finance reports'])) return route('finance.dashboard');

        return route('legal.terms');
    }
}
