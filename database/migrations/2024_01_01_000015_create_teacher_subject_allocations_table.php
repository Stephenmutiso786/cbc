<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teacher_subject_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('staff_members')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('learning_area_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('term')->default(1);
            $table->string('academic_year', 9);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'class_id', 'learning_area_id', 'term', 'academic_year'], 'teacher_subject_class_term_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subject_allocations');
    }
};
