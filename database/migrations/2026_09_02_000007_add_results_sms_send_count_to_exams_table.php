<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('exams', 'results_sms_send_count')) {
            Schema::table('exams', function (Blueprint $table): void {
                $table->unsignedTinyInteger('results_sms_send_count')->default(0)->after('results_sms_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exams', 'results_sms_send_count')) {
            Schema::table('exams', function (Blueprint $table): void {
                $table->dropColumn('results_sms_send_count');
            });
        }
    }
};
