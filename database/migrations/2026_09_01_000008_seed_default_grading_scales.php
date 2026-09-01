<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('grading_scales') || ! Schema::hasTable('school_classes')) {
            return;
        }

        $definitions = [
            ['name' => 'CBC Lower Primary (Grades 1-3)', 'description' => 'Qualitative four-level CBC rubric for lower primary.', 'type' => 'rubric', 'bands' => [
                ['code' => 'EE', 'min' => 75, 'max' => 100, 'label' => 'Exceeds Expectations'], ['code' => 'ME', 'min' => 50, 'max' => 74.99, 'label' => 'Meets Expectations'], ['code' => 'AE', 'min' => 30, 'max' => 49.99, 'label' => 'Approaching Expectations'], ['code' => 'BE', 'min' => 0, 'max' => 29.99, 'label' => 'Below Expectations'],
            ], 'grades' => ['Grade 1', 'Grade 2', 'Grade 3']],
            ['name' => 'CBC Upper Primary (Grades 4-6)', 'description' => 'Four-point CBC scale: EE 4, ME 3, AE 2, BE 1.', 'type' => 'rubric', 'bands' => [
                ['code' => 'EE', 'min' => 80, 'max' => 100, 'points' => 4, 'label' => 'Exceeds Expectations'], ['code' => 'ME', 'min' => 50, 'max' => 79.99, 'points' => 3, 'label' => 'Meets Expectations'], ['code' => 'AE', 'min' => 30, 'max' => 49.99, 'points' => 2, 'label' => 'Approaching Expectations'], ['code' => 'BE', 'min' => 0, 'max' => 29.99, 'points' => 1, 'label' => 'Below Expectations'],
            ], 'grades' => ['Grade 4', 'Grade 5', 'Grade 6']],
            ['name' => 'KJSEA Eight-Point Scale (Grades 7-9)', 'description' => 'Eight-point achievement scale for junior secondary.', 'type' => 'letter', 'bands' => [
                ['code' => 'EE1', 'min' => 90, 'max' => 100, 'points' => 8, 'label' => 'Exceptional'], ['code' => 'EE2', 'min' => 75, 'max' => 89.99, 'points' => 7, 'label' => 'Very Good'], ['code' => 'ME1', 'min' => 58, 'max' => 74.99, 'points' => 6, 'label' => 'Good'], ['code' => 'ME2', 'min' => 41, 'max' => 57.99, 'points' => 5, 'label' => 'Fair'], ['code' => 'AE1', 'min' => 31, 'max' => 40.99, 'points' => 4, 'label' => 'Needs Improvement'], ['code' => 'AE2', 'min' => 21, 'max' => 30.99, 'points' => 3, 'label' => 'Below Average'], ['code' => 'BE1', 'min' => 11, 'max' => 20.99, 'points' => 2, 'label' => 'Well Below Average'], ['code' => 'BE2', 'min' => 0, 'max' => 10.99, 'points' => 1, 'label' => 'Minimal Progress'],
            ], 'grades' => ['Grade 7', 'Grade 8', 'Grade 9']],
        ];

        $year = (string) config('school.academic_year');
        foreach ($definitions as $definition) {
            $scale = DB::table('grading_scales')->where('name', $definition['name'])->first();
            $scaleId = $scale?->id ?? DB::table('grading_scales')->insertGetId([
                'name' => $definition['name'], 'description' => $definition['description'], 'type' => $definition['type'],
                'bands' => json_encode($definition['bands']), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('school_classes')->whereIn('grade_level', $definition['grades'])->get(['id'])->each(function ($class) use ($scaleId, $year): void {
                $hasScale = DB::table('class_grading_scales')->where(['class_id' => $class->id, 'academic_year' => $year])->exists();
                if (! $hasScale) {
                    DB::table('class_grading_scales')->insert(['class_id' => $class->id, 'grading_scale_id' => $scaleId, 'academic_year' => $year, 'created_at' => now(), 'updated_at' => now()]);
                }
            });
        }
    }

    public function down(): void
    {
        // Keep grading history and administrator-created scales intact.
    }
};
