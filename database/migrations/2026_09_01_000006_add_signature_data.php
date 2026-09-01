<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('staff_members', 'signature_data')) {
            Schema::table('staff_members', fn (Blueprint $table) => $table->longText('signature_data')->nullable()->after('is_active'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('staff_members', 'signature_data')) {
            Schema::table('staff_members', fn (Blueprint $table) => $table->dropColumn('signature_data'));
        }
    }
};
