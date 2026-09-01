<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['letter', 'rubric'])->default('letter');
            $table->json('bands');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('class_grading_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('grading_scale_id')->constrained('grading_scales')->cascadeOnDelete();
            $table->string('academic_year', 9);
            $table->timestamps();
            $table->unique(['class_id', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_grading_scales');
        Schema::dropIfExists('grading_scales');
    }
};
