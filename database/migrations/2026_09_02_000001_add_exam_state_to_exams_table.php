<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('exams', 'exam_state')) {
            Schema::table('exams', function (Blueprint $table): void {
                $table->string('exam_state', 20)->default('draft')->after('status');
                $table->index(['academic_year', 'term', 'exam_state']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exams', 'exam_state')) {
            Schema::table('exams', function (Blueprint $table): void {
                $table->dropIndex(['academic_year', 'term', 'exam_state']);
                $table->dropColumn('exam_state');
            });
        }
    }
};
