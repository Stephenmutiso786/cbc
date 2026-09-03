<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('exam_timetable', 'is_published')) {
            Schema::table('exam_timetable', function (Blueprint $table): void {
                $table->boolean('is_published')->default(false)->index();
                $table->index(['date', 'start_time', 'class_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_timetable', 'is_published')) {
            Schema::table('exam_timetable', function (Blueprint $table): void {
                $table->dropIndex(['date', 'start_time', 'class_id']);
                $table->dropColumn('is_published');
            });
        }
    }
};
