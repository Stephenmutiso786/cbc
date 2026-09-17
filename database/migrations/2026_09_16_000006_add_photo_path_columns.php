<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('admission_number');
        });
        Schema::table('staff_members', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('staff_number');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
        Schema::table('staff_members', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};

