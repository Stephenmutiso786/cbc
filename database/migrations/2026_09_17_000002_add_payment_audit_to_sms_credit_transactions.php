<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_credit_transactions', function (Blueprint $table): void {
            $table->decimal('amount_paid', 12, 2)->nullable()->after('amount');
            $table->string('payment_reference', 100)->nullable()->after('amount_paid');
        });
    }

    public function down(): void
    {
        Schema::table('sms_credit_transactions', function (Blueprint $table): void {
            $table->dropColumn(['amount_paid', 'payment_reference']);
        });
    }
};
