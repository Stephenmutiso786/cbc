<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('minimum_average', 5, 2)->nullable();
            $table->string('from_grade');
            $table->string('to_grade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('learner_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_class_id')->constrained('school_classes');
            $table->foreignId('to_class_id')->constrained('school_classes');
            $table->string('from_academic_year', 9);
            $table->string('to_academic_year', 9);
            $table->foreignId('promotion_rule_id')->nullable()->constrained('promotion_rules')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['learner_id', 'from_academic_year', 'to_academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_promotions');
        Schema::dropIfExists('promotion_rules');
    }
};
