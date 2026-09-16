<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const KEYS = ['maintenance_mode','maintenance_message','legal_policy_version','legal_terms_content','legal_privacy_content'];
    public function up(): void
    {
        foreach (self::KEYS as $key) {
            $row = DB::table('school_settings')->where('key', $key)->orderBy('id')->first();
            if ($row) DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => $row->value, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('school_settings')->whereIn('key', self::KEYS)->delete();
    }
    public function down(): void { /* global settings intentionally stay global */ }
};
