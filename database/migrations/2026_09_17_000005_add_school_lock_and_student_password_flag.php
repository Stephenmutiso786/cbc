<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            if (! Schema::hasColumn('schools', 'is_locked')) $table->boolean('is_locked')->default(false)->after('is_active');
        });
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'must_change_password')) $table->boolean('must_change_password')->default(false)->after('password');
        });
    }
    public function down(): void {}
};
