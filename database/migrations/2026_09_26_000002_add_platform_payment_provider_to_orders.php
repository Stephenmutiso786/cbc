<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['subscription_payments', 'sms_credit_orders'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'payment_provider')) {
                Schema::table($table, fn (Blueprint $table) => $table->string('payment_provider', 30)->default('mpesa')->after('phone'));
            }
        }
    }
    public function down(): void
    {
        foreach (['subscription_payments', 'sms_credit_orders'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'payment_provider')) Schema::table($table, fn (Blueprint $table) => $table->dropColumn('payment_provider'));
        }
    }
};
