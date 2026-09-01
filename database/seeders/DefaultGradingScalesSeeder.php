<?php

namespace Database\Seeders;

use App\Models\GradingScale;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class DefaultGradingScalesSeeder extends Seeder
{
    public function run(): void
    {
        $scales = [
            [
                'name' => 'CBC Lower Primary (Grades 1-3)',
                'description' => 'Qualitative four-level CBC rubric for lower primary.',
                'type' => 'rubric',
                'bands' => [
                    ['code' => 'EE', 'min' => 75, 'max' => 100, 'label' => 'Exceeds Expectations'],
                    ['code' => 'ME', 'min' => 50, 'max' => 74.99, 'label' => 'Meets Expectations'],
                    ['code' => 'AE', 'min' => 30, 'max' => 49.99, 'label' => 'Approaching Expectations'],
                    ['code' => 'BE', 'min' => 0, 'max' => 29.99, 'label' => 'Below Expectations'],
                ],
                'grades' => ['Grade 1', 'Grade 2', 'Grade 3'],
            ],
            [
                'name' => 'CBC Upper Primary (Grades 4-6)',
                'description' => 'Four-point CBC scale: EE 4, ME 3, AE 2, BE 1.',
                'type' => 'rubric',
                'bands' => [
                    ['code' => 'EE', 'min' => 80, 'max' => 100, 'points' => 4, 'label' => 'Exceeds Expectations'],
                    ['code' => 'ME', 'min' => 50, 'max' => 79.99, 'points' => 3, 'label' => 'Meets Expectations'],
                    ['code' => 'AE', 'min' => 30, 'max' => 49.99, 'points' => 2, 'label' => 'Approaching Expectations'],
                    ['code' => 'BE', 'min' => 0, 'max' => 29.99, 'points' => 1, 'label' => 'Below Expectations'],
                ],
                'grades' => ['Grade 4', 'Grade 5', 'Grade 6'],
            ],
            [
                'name' => 'KJSEA Eight-Point Scale (Grades 7-9)',
                'description' => 'Eight-point achievement scale for junior secondary.',
                'type' => 'letter',
                'bands' => [
                    ['code' => 'EE1', 'min' => 90, 'max' => 100, 'points' => 8, 'label' => 'Exceptional'],
                    ['code' => 'EE2', 'min' => 75, 'max' => 89.99, 'points' => 7, 'label' => 'Very Good'],
                    ['code' => 'ME1', 'min' => 58, 'max' => 74.99, 'points' => 6, 'label' => 'Good'],
                    ['code' => 'ME2', 'min' => 41, 'max' => 57.99, 'points' => 5, 'label' => 'Fair'],
                    ['code' => 'AE1', 'min' => 31, 'max' => 40.99, 'points' => 4, 'label' => 'Needs Improvement'],
                    ['code' => 'AE2', 'min' => 21, 'max' => 30.99, 'points' => 3, 'label' => 'Below Average'],
                    ['code' => 'BE1', 'min' => 11, 'max' => 20.99, 'points' => 2, 'label' => 'Well Below Average'],
                    ['code' => 'BE2', 'min' => 0, 'max' => 10.99, 'points' => 1, 'label' => 'Minimal Progress'],
                ],
                'grades' => ['Grade 7', 'Grade 8', 'Grade 9'],
            ],
        ];

        foreach ($scales as $definition) {
            $scale = GradingScale::firstOrCreate(
                ['name' => $definition['name']],
                collect($definition)->except('grades')->all()
            );

            foreach (SchoolClass::whereIn('grade_level', $definition['grades'])->get() as $class) {
                if (! $class->gradingScales()->wherePivot('academic_year', (string) config('school.academic_year'))->exists()) {
                    $class->gradingScales()->attach($scale->id, ['academic_year' => (string) config('school.academic_year')]);
                }
            }
        }
    }
}
