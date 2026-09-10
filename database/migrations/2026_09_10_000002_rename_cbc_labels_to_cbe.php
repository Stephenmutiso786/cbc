<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('grading_scales')) {
            DB::table('grading_scales')
                ->where('name', 'CBC Lower Primary (Grades 1-3)')
                ->update([
                    'name' => 'CBE Lower Primary (Grades 1-3)',
                    'description' => 'Qualitative four-level CBE rubric for lower primary.',
                ]);
            DB::table('grading_scales')
                ->where('name', 'CBC Upper Primary (Grades 4-6)')
                ->update([
                    'name' => 'CBE Upper Primary (Grades 4-6)',
                    'description' => 'Four-point CBE scale: EE 4, ME 3, AE 2, BE 1.',
                ]);
        }

        if (Schema::hasTable('school_settings')) {
            DB::table('school_settings')
                ->whereIn('key', ['name', 'school_name'])
                ->where('value', 'CBC School Management System')
                ->update(['value' => 'CBE School Management System']);
        }
    }

    public function down(): void
    {
        // Keep the new CBE public branding when rolling back unrelated changes.
    }
};
