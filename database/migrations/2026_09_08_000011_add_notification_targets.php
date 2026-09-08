<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('school_notifications', function (Blueprint $table): void {
            $table->string('target_group', 20)->default('all')->after('target_grade');
            $table->foreignId('target_class_id')->nullable()->after('target_group')->constrained('school_classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_notifications', function (Blueprint $table): void {
            $table->dropForeign(['target_class_id']);
            $table->dropColumn(['target_group', 'target_class_id']);
        });
    }
};
