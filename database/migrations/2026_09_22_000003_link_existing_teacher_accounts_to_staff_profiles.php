<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Repair historical teacher logins that were created separately from
     * their staff profile. Teacher subject allocations point to the staff
     * profile, not the user row, so an absent link made real allocations look
     * like zero subjects in the teacher portal.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('staff_members')
            || ! Schema::hasColumn('users', 'school_id') || ! Schema::hasColumn('staff_members', 'school_id')) {
            return;
        }

        DB::table('users')
            ->select(['id', 'school_id', 'email'])
            ->whereNotNull('school_id')
            ->whereNotNull('email')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                $staff = DB::table('staff_members')
                    ->where('school_id', $user->school_id)
                    ->whereNull('user_id')
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $user->email)])
                    ->orderBy('id')
                    ->first();

                if ($staff) {
                    DB::table('staff_members')->where('id', $staff->id)->update([
                        'user_id' => $user->id,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The repaired links are valid account ownership data and must not be
        // removed during rollback.
    }
};
