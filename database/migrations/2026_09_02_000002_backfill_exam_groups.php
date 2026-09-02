<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('exams', 'exam_group_id')) {
            return;
        }

        DB::table('exams')->select('name', 'class_id', 'academic_year', 'term')
            ->whereNull('exam_group_id')
            ->groupBy('name', 'class_id', 'academic_year', 'term')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($group): void {
                $ids = DB::table('exams')
                    ->whereNull('exam_group_id')
                    ->where('name', $group->name)
                    ->where('class_id', $group->class_id)
                    ->where('academic_year', $group->academic_year)
                    ->where('term', $group->term)
                    ->orderBy('id')
                    ->pluck('id');

                if ($ids->count() > 1) {
                    DB::table('exams')->whereIn('id', $ids->skip(1))->update(['exam_group_id' => $ids->first()]);
                }
            });
    }

    public function down(): void
    {
        // Group assignments are data corrections and should not be undone.
    }
};
