<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    private const TABLES = ['learners','guardians','staff_members','school_classes','learning_areas','strands','sub_strands','assessments','attendance','exams','exam_results','exam_timetable','exam_report_exports','fee_structures','fee_invoices','fee_payments','bursaries','inventory_categories','inventory_items','inventory_transactions','learning_notes','lesson_plans','school_notifications','notification_logs','portfolio_items','promotion_rules','learner_promotions','teacher_subject_allocations','timetable_slots','grading_scales','academic_years','academic_terms','school_settings','school_setting_assets','kemis_sync_logs','data_transfer_usages','support_tickets'];

    public function up(): void
    {
        $setting = static fn (string $key) => Schema::hasTable('school_settings') ? DB::table('school_settings')->where('key', $key)->value('value') : null;
        $name = (string) (($setting('name')) ?: env('SCHOOL_NAME', 'CBE School Management System'));
        $slug = Str::slug($name) ?: 'default-school';
        if (DB::table('schools')->where('slug', $slug)->exists()) $slug .= '-' . Str::random(4);
        $schoolId = DB::table('schools')->insertGetId(['name' => $name, 'slug' => $slug, 'type' => (string) (($setting('type')) ?: 'primary'), 'motto' => $setting('motto'), 'address' => $setting('address'), 'phone' => $setting('phone'), 'email' => $setting('email'), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (self::TABLES as $table) if (Schema::hasTable($table) && Schema::hasColumn($table, 'school_id')) DB::table($table)->whereNull('school_id')->update(['school_id' => $schoolId]);
        $superAdmins = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', 'super-admin')->where('model_has_roles.model_type', User::class)->pluck('model_has_roles.model_id');
        DB::table('users')->whereNull('school_id')->whereNotIn('id', $superAdmins)->update(['school_id' => $schoolId]);
    }
    public function down(): void { /* irreversible data backfill */ }
};
