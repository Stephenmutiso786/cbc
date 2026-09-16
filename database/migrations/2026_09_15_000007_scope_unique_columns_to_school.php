<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const COLUMNS = ['learners' => 'admission_number','staff_members' => 'staff_number','learning_areas' => 'code','fee_invoices' => 'invoice_number','fee_payments' => 'receipt_number','inventory_items' => 'code','academic_years' => 'year','data_transfer_usages' => 'usage_date','school_settings' => 'key','school_setting_assets' => 'key'];
    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'school_id') || !Schema::hasColumn($table, $column)) continue;
            Schema::table($table, function (Blueprint $blueprint) use ($table, $column): void {
                try { $blueprint->dropUnique($table . '_' . $column . '_unique'); } catch (\Throwable) { }
                $blueprint->unique(['school_id', $column], $table . '_school_' . $column . '_unique');
            });
        }
    }
    public function down(): void { /* tenant-safe uniqueness is not reverted automatically */ }
};
