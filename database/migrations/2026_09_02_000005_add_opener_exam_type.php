<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private array $types = ['cat', 'opener', 'mid_term', 'end_term', 'mock', 'kpsea', 'kcse'];

    public function up(): void
    {
        $quoted = implode(', ', array_map(fn (string $type) => "'{$type}'", $this->types));

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE exams DROP CONSTRAINT IF EXISTS exams_exam_type_check');
            DB::statement("ALTER TABLE exams ADD CONSTRAINT exams_exam_type_check CHECK (exam_type IN ({$quoted}))");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE exams MODIFY exam_type ENUM({$quoted}) NOT NULL DEFAULT 'end_term'");
        }
    }

    public function down(): void
    {
        $types = ['cat', 'mid_term', 'end_term', 'mock', 'kpsea', 'kcse'];
        $quoted = implode(', ', array_map(fn (string $type) => "'{$type}'", $types));

        if (DB::getDriverName() === 'pgsql') {
            DB::table('exams')->where('exam_type', 'opener')->update(['exam_type' => 'cat']);
            DB::statement('ALTER TABLE exams DROP CONSTRAINT IF EXISTS exams_exam_type_check');
            DB::statement("ALTER TABLE exams ADD CONSTRAINT exams_exam_type_check CHECK (exam_type IN ({$quoted}))");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::table('exams')->where('exam_type', 'opener')->update(['exam_type' => 'cat']);
            DB::statement("ALTER TABLE exams MODIFY exam_type ENUM({$quoted}) NOT NULL DEFAULT 'end_term'");
        }
    }
};
