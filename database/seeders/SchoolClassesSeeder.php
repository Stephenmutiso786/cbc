<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class SchoolClassesSeeder extends Seeder
{
    public function run(): void
    {
        $year = (string) config('school.academic_year');

        foreach (array_merge(...array_values(config('school.grade_levels'))) as $grade) {
            SchoolClass::firstOrCreate(
                ['name' => $grade, 'grade_level' => $grade, 'academic_year' => $year],
                ['capacity' => 45, 'is_active' => true]
            );
        }
    }
}
