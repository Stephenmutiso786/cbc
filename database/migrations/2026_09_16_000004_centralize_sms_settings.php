<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * SMS is now bought/managed centrally: the super-admin holds the one
     * provider account for the whole platform, and each school just has a
     * credit balance (schools.sms_credits) that the super-admin tops up
     * when a school pays. Fee collection (M-Pesa) stays per-school on
     * purpose — that money goes straight to each school's own till.
     */
    private array $globalKeys = ['olympus_sms_api_url', 'olympus_sms_api_token', 'olympus_sms_sender_id', 'at_api_key'];

    public function up(): void
    {
        // Only one school exists at this point in a fresh upgrade, so take
        // whichever row already has a value (if any) as the starting point.
        foreach ($this->globalKeys as $key) {
            $row = DB::table('school_settings')->where('key', $key)->whereNotNull('value')->orderBy('id')->first()
                ?? DB::table('school_settings')->where('key', $key)->orderBy('id')->first();
            if ($row) {
                DB::table('system_settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $row->value, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        DB::table('school_settings')->whereIn('key', $this->globalKeys)->delete();
    }

    public function down(): void
    {
        // Not reversible — these keys are intentionally no longer per-school.
    }
};

