<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->change();
        });

        Schema::table('staff_members', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable(false)->change();
        });

        Schema::table('staff_members', function (Blueprint $table) {
            $table->string('phone_number')->nullable(false)->change();
        });
    }
};
