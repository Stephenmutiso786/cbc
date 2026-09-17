<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('id')->constrained('packages')->nullOnDelete();
            $table->date('package_expires_at')->nullable()->after('package_id');
            $table->unsignedInteger('sms_credits')->default(0)->after('package_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn(['package_expires_at', 'sms_credits']);
        });
    }
};

