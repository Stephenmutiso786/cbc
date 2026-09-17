<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Plans must never manufacture SMS units. Retain only allocations with a
     * payment amount and reference, then recompute every school wallet from
     * that auditable ledger. Historical virtual package grants are removed.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('packages')->update(['sms_credits_granted' => 0, 'updated_at' => now()]);

            DB::table('sms_credit_transactions')
                ->where('type', 'package_grant')
                ->orWhere(function ($query): void {
                    $query->where('type', 'topup')->where(function ($topup): void {
                        $topup->whereNull('amount_paid')->orWhere('amount_paid', '<=', 0)->orWhereNull('payment_reference')->orWhere('payment_reference', '');
                    });
                })->delete();

            DB::table('schools')->orderBy('id')->each(function (object $school): void {
                $balance = (int) DB::table('sms_credit_transactions')->where('school_id', $school->id)->sum('amount');
                DB::table('schools')->where('id', $school->id)->update(['sms_credits' => max(0, $balance), 'updated_at' => now()]);
            });
        });
    }

    public function down(): void
    {
        // Removed virtual credits cannot safely be reconstructed.
    }
};
