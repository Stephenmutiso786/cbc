<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\School;
use App\Support\Tenant;
use Illuminate\Support\Str;

/** Creates school-owned admission numbers, e.g. MHS-2026-0001. */
class SchoolAdmissionNumberService
{
    public function prefix(): string
    {
        $schoolId = Tenant::id() ?? auth()->user()?->school_id;
        $name = $schoolId ? School::query()->find($schoolId)?->name : config('school.name');
        $words = preg_split('/[^[:alnum:]]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = collect($words)->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))->implode('');

        return Str::limit($initials ?: 'SCH', 6, '');
    }

    public function next(): string
    {
        $prefix = $this->prefix();
        $year = (string) config('school.academic_year');
        $base = $prefix . '-' . $year . '-';
        $next = Learner::withTrashed()->where('admission_number', 'like', $base . '%')->count() + 1;

        do {
            $number = $base . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Learner::withTrashed()->where('admission_number', $number)->exists());

        return $number;
    }

    public function belongsToCurrentSchool(string $number): bool
    {
        return Str::startsWith(Str::upper(trim($number)), $this->prefix() . '-');
    }
}
