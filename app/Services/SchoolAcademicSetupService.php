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
