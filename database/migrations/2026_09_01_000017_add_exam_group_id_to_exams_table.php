<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->foreignId('exam_group_id')->nullable()->after('id')->constrained('exams')->nullOnDelete();
            $table->index(['exam_group_id', 'class_id', 'academic_year', 'term']);
        });

        DB::table('exams')->select('name', 'class_id', 'academic_year', 'term')->groupBy('name', 'class_id', 'academic_year', 'term')->havingRaw('COUNT(*) > 1')->get()->each(function ($group): void {
            $ids = DB::table('exams')->where('name', $group->name)->where('class_id', $group->class_id)->where('academic_year', $group->academic_year)->where('term', $group->term)->orderBy('id')->pluck('id');
            $masterId = $ids->first();
            DB::table('exams')->whereIn('id', $ids->skip(1))->update(['exam_group_id' => $masterId]);
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->dropForeign(['exam_group_id']);
            $table->dropIndex(['exam_group_id', 'class_id', 'academic_year', 'term']);
            $table->dropColumn('exam_group_id');
        });
    }
};
