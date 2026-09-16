<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public const TABLES = ['users','learners','guardians','staff_members','school_classes','learning_areas','strands','sub_strands','assessments','attendance','exams','exam_results','exam_timetable','exam_report_exports','fee_structures','fee_invoices','fee_payments','bursaries','inventory_categories','inventory_items','inventory_transactions','learning_notes','lesson_plans','school_notifications','notification_logs','portfolio_items','promotion_rules','learner_promotions','teacher_subject_allocations','timetable_slots','grading_scales','academic_years','academic_terms','school_settings','school_setting_assets','kemis_sync_logs','data_transfer_usages','support_tickets'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'school_id')) continue;
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('school_id')->nullable()->after('id')->constrained('schools')->restrictOnDelete();
                $blueprint->index('school_id');
            });
        }
    }
    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'school_id')) continue;
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId('school_id'));
        }
    }
};
