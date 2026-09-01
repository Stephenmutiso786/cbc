<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Keep historical results intact, but remove automatic school setup.
        DB::table('teacher_subject_allocations')->delete();
        DB::table('class_learning_areas')->delete();
        DB::table('learning_areas')->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Subject assignments are school-specific and cannot be reconstructed safely.
    }
};
