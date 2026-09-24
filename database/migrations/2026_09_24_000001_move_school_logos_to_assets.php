<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Move existing data-URI logos out of the short settings value column. */
    public function up(): void
    {
        if (! Schema::hasTable('school_settings') || ! Schema::hasTable('school_setting_assets')) {
            return;
        }

        DB::table('school_settings')->where('key', 'logo_data')->orderBy('id')->get()->each(function (object $setting): void {
            $logo = (string) $setting->value;
            if (! str_starts_with($logo, 'data:image/') || ! $setting->school_id) {
                return;
            }

            DB::table('school_setting_assets')->updateOrInsert(
                ['school_id' => $setting->school_id, 'key' => 'logo_data'],
                ['data' => $logo, 'updated_at' => now(), 'created_at' => now()]
            );

            DB::table('school_settings')->where('id', $setting->id)->delete();
        });
    }

    public function down(): void
    {
        // Assets remain valid school records; avoid moving a large image back
        // into the limited settings-value storage during rollback.
    }
};
