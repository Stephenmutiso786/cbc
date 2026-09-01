<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('class_learning_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('learning_area_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('lessons_per_week')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['class_id', 'learning_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_learning_areas');
    }
};
