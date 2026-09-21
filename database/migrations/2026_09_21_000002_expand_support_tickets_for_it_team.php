<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('support_tickets', function (Blueprint $table) { $table->string('module', 60)->nullable()->after('subject'); $table->string('device', 120)->nullable()->after('module'); $table->text('error_message')->nullable()->after('description'); $table->text('additional_info')->nullable()->after('error_message'); }); }
    public function down(): void { Schema::table('support_tickets', function (Blueprint $table) { $table->dropColumn(['module','device','error_message','additional_info']); }); }
};
