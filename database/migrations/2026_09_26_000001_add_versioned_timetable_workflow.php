<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('timetable_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('academic_year', 9);
            $table->string('term', 20);
            $table->unsignedInteger('version_number');
            $table->string('state', 20)->default('draft');
            $table->json('generation_options')->nullable();
            $table->json('conflict_report')->nullable();
            $table->unsignedInteger('lesson_count')->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'academic_year', 'term', 'version_number'], 'timetable_version_per_term_unique');
            $table->index(['school_id', 'academic_year', 'term', 'state'], 'timetable_version_lookup');
        });

        Schema::create('timetable_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type', 20); // teacher, class, venue
            $table->string('resource_key', 120);
            $table->string('day_of_week', 20);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(false);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'resource_type', 'resource_key', 'day_of_week'], 'timetable_availability_lookup');
        });

        Schema::table('timetable_slots', function (Blueprint $table): void {
            $table->foreignId('timetable_version_id')->nullable()->after('id')->constrained('timetable_versions')->nullOnDelete();
            $table->boolean('is_double')->default(false)->after('venue');
            $table->index(['school_id', 'timetable_version_id', 'class_id'], 'timetable_slots_version_class');
            $table->index(['school_id', 'timetable_version_id', 'teacher_id'], 'timetable_slots_version_teacher');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table): void {
            $table->dropIndex('timetable_slots_version_class');
            $table->dropIndex('timetable_slots_version_teacher');
            $table->dropConstrainedForeignId('timetable_version_id');
            $table->dropColumn('is_double');
        });
        Schema::dropIfExists('timetable_availabilities');
        Schema::dropIfExists('timetable_versions');
    }
};
