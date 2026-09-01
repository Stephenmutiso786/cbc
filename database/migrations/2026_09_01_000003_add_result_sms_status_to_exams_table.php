<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('exams', 'results_sms_status')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->enum('results_sms_status', ['not_sent', 'queued', 'sent', 'partial', 'failed'])
                    ->default('not_sent')->after('results_locked_at');
                $table->timestamp('results_sms_queued_at')->nullable()->after('results_sms_status');
                $table->timestamp('results_sms_sent_at')->nullable()->after('results_sms_queued_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exams', 'results_sms_status')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropColumn(['results_sms_status', 'results_sms_queued_at', 'results_sms_sent_at']);
            });
        }
    }
};
