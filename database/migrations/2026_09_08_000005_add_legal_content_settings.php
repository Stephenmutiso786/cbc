<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('school_settings')->updateOrInsert(
            ['key' => 'legal_policy_version'],
            ['value' => config('legal.version'), 'created_at' => now(), 'updated_at' => now()]
        );
        foreach (['legal_terms_content', 'legal_privacy_content'] as $key) {
            DB::table('school_settings')->updateOrInsert(['key' => $key], ['value' => '', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('school_settings')->whereIn('key', ['legal_policy_version', 'legal_terms_content', 'legal_privacy_content'])->delete();
    }
};
