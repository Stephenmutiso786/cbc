<?php

namespace App\Services;

use App\Models\SchoolClass;
use Database\Seeders\DefaultClassSubjectsSeeder;
use Database\Seeders\DefaultGradingScalesSeeder;

/**
 * Non-destructively completes the CBE academic configuration for a class.
 *
 * It never removes custom learning areas or replaces a school's selected
 * scale. It only adds missing standard subjects and the missing active
 * scale for the class's current academic year.
 */
class SchoolAcademicSetupService
{
    /**
     * Gives a newly provisioned school a usable CBE academic structure.
     *
     * A class is identified by its grade and academic year rather than its
     * display name. That means an imported stream such as "Grade 4 East"
     * counts as the school's Grade 4 and will not be duplicated by this
     * initializer. Subjects and the active grading scale are then completed
     * for every configured class in the school.
     */
    public function initializeCurrentSchool(): int
    {
        $academicYear = (string) config('school.academic_year');

        // The subject catalogue currently covers the CBE Grade 1–9 bands.
        // Do not create PP1/PP2 placeholders with no available subjects.
        foreach (array_merge(
            config('school.grade_levels.lower_primary', []),
            config('school.grade_levels.upper_primary', []),
            config('school.grade_levels.junior_secondary', []),
        ) as $grade) {
            SchoolClass::firstOrCreate(
                ['grade_level' => $grade, 'academic_year' => $academicYear],
                [
                    'name' => $grade,
                    'capacity' => 45,
                    'is_active' => true,
                ],
            );
        }

        return $this->repairCurrentSchool();
    }

    public function repairClass(SchoolClass $class): SchoolClass
    {
        app(DefaultClassSubjectsSeeder::class)->seedForClass($class);
        app(DefaultGradingScalesSeeder::class)->run();

        return $class->fresh(['learningAreas', 'gradingScales']);
    }

    public function repairCurrentSchool(): int
    {
        $classes = SchoolClass::forConfiguredGrades()->get();
        foreach ($classes as $class) {
            app(DefaultClassSubjectsSeeder::class)->seedForClass($class);
        }
        app(DefaultGradingScalesSeeder::class)->run();

        return $classes->count();
    }
}
