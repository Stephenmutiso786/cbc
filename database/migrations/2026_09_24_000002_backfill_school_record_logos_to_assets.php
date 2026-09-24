<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Schools onboarded before the settings-assets store have their logo on
     * schools.logo_data. Copy that source into the tenant asset store without
     * replacing a logo a school has uploaded more recently in Settings.
     */
    public function up(): void
    {
        if (! Schema::hasTable('schools') || ! Schema::hasTable('school_setting_assets')
            || ! Schema::hasColumn('schools', 'logo_data')
            || ! Schema::hasColumn('school_setting_assets', 'school_id')) {
            return;
        }

        DB::table('schools')->whereNotNull('logo_data')->orderBy('id')->each(function (object $school): void {
            $logo = (string) $school->logo_data;
            if (! str_starts_with($logo, 'data:image/')) {
                return;
            }

            DB::table('school_setting_assets')->updateOrInsert(
                ['school_id' => $school->id, 'key' => 'logo_data'],
                ['data' => $logo, 'created_at' => now(), 'updated_at' => now()]
            );
        });
    }

    public function down(): void
    {
        // The copied image remains a valid school-owned branding asset.
    }
};
