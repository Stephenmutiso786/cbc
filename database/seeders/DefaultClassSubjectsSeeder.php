<?php

namespace Database\Seeders;

use App\Models\LearningArea;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class DefaultClassSubjectsSeeder extends Seeder
{
    private const SUBJECTS = [
        'Grade 1' => [
            ['English', 'ENG-G1'], ['Kiswahili', 'KIS-G1'], ['Mathematics', 'MAT-G1'],
            ['Environmental Activities', 'ENV-G1'], ['Creative Activities', 'CRA-G1'],
            ['Indigenous Language', 'IND-G1'], ['Christian Religious Education (CRE)', 'CRE-G1'],
        ],
        'Grade 2' => [
            ['English', 'ENG-G2'], ['Kiswahili', 'KIS-G2'], ['Mathematics', 'MAT-G2'],
            ['Environmental Activities', 'ENV-G2'], ['Creative Activities', 'CRA-G2'],
            ['Indigenous Language', 'IND-G2'], ['Christian Religious Education (CRE)', 'CRE-G2'],
        ],
        'Grade 3' => [
            ['English', 'ENG-G3'], ['Kiswahili', 'KIS-G3'], ['Mathematics', 'MAT-G3'],
            ['Environmental Activities', 'ENV-G3'], ['Creative Activities', 'CRA-G3'],
            ['Indigenous Language', 'IND-G3'], ['Christian Religious Education (CRE)', 'CRE-G3'],
        ],
        'Grade 4' => [
            ['English', 'ENG-G4'], ['Kiswahili', 'KIS-G4'], ['Mathematics', 'MAT-G4'],
            ['Science and Technology', 'SCI-G4'], ['Social Studies', 'SST-G4'],
            ['Agriculture and Nutrition', 'AGN-G4'], ['Creative Arts', 'CRA-G4'],
            ['Christian Religious Education (CRE)', 'CRE-G4'],
        ],
        'Grade 5' => [
            ['English', 'ENG-G5'], ['Kiswahili', 'KIS-G5'], ['Mathematics', 'MAT-G5'],
            ['Science and Technology', 'SCI-G5'], ['Social Studies', 'SST-G5'],
            ['Agriculture and Nutrition', 'AGN-G5'], ['Creative Arts', 'CRA-G5'],
            ['Christian Religious Education (CRE)', 'CRE-G5'],
        ],
        'Grade 6' => [
            ['English', 'ENG-G6'], ['Kiswahili', 'KIS-G6'], ['Mathematics', 'MAT-G6'],
            ['Science and Technology', 'SCI-G6'], ['Social Studies', 'SST-G6'],
            ['Agriculture and Nutrition', 'AGN-G6'], ['Creative Arts', 'CRA-G6'],
            ['Christian Religious Education (CRE)', 'CRE-G6'],
        ],
        'Grade 7' => [
            ['English', 'ENG-G7'], ['Kiswahili', 'KIS-G7'], ['Mathematics', 'MAT-G7'],
            ['Integrated Science', 'SCI-G7'], ['Social Studies', 'SST-G7'],
            ['Pre-Technical Studies', 'PTS-G7'], ['Agriculture and Nutrition', 'AGN-G7'],
            ['Creative Arts and Sports', 'CAS-G7'], ['Christian Religious Education (CRE)', 'CRE-G7'],
        ],
        'Grade 8' => [
            ['English', 'ENG-G8'], ['Kiswahili', 'KIS-G8'], ['Mathematics', 'MAT-G8'],
            ['Integrated Science', 'SCI-G8'], ['Social Studies', 'SST-G8'],
            ['Pre-Technical Studies', 'PTS-G8'], ['Agriculture and Nutrition', 'AGN-G8'],
            ['Creative Arts and Sports', 'CAS-G8'], ['Christian Religious Education (CRE)', 'CRE-G8'],
        ],
        'Grade 9' => [
            ['English', 'ENG-G9'], ['Kiswahili', 'KIS-G9'], ['Mathematics', 'MAT-G9'],
            ['Integrated Science', 'SCI-G9'], ['Social Studies', 'SST-G9'],
            ['Pre-Technical Studies', 'PTS-G9'], ['Agriculture and Nutrition', 'AGN-G9'],
            ['Creative Arts and Sports', 'CAS-G9'], ['Christian Religious Education (CRE)', 'CRE-G9'],
        ],
    ];

    public function run(): void
    {
        foreach (SchoolClass::forConfiguredGrades()->get() as $class) {
            $this->seedForClass($class);
        }

        $this->command?->info('Grade 1-9 class subjects seeded and assigned.');
    }

    public function seedForClass(SchoolClass $class): void
    {
        $subjectIds = [];

        foreach (self::SUBJECTS[$class->grade_level] ?? [] as [$name, $code]) {
            $area = LearningArea::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'grade_level' => $class->grade_level,
                    'weekly_lessons' => 5,
                    'is_active' => true,
                ],
            );
            $subjectIds[$area->id] = ['lessons_per_week' => $area->weekly_lessons, 'is_active' => true];
        }

        if ($subjectIds !== []) {
            $class->learningAreas()->syncWithoutDetaching($subjectIds);
        }
    }
}
