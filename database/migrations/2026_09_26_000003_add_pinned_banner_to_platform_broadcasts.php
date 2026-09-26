<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { if (Schema::hasTable('platform_broadcasts') && ! Schema::hasColumn('platform_broadcasts','is_pinned')) Schema::table('platform_broadcasts', fn (Blueprint $table) => $table->boolean('is_pinned')->default(false)->after('send_sms')); } public function down(): void { if (Schema::hasTable('platform_broadcasts') && Schema::hasColumn('platform_broadcasts','is_pinned')) Schema::table('platform_broadcasts', fn (Blueprint $table) => $table->dropColumn('is_pinned')); } };
