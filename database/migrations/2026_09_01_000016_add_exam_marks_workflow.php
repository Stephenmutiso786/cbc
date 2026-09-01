<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('marks_status', 20)->default('draft')->after('status');
            $table->timestamp('marks_submitted_at')->nullable()->after('marks_status');
            $table->foreignId('marks_submitted_by')->nullable()->after('marks_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('marks_reviewed_at')->nullable()->after('marks_submitted_by');
            $table->foreignId('marks_reviewed_by')->nullable()->after('marks_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('marks_review_comment')->nullable()->after('marks_reviewed_by');
            $table->index(['academic_year', 'term', 'marks_status']);
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['marks_submitted_by']);
            $table->dropForeign(['marks_reviewed_by']);
            $table->dropIndex(['academic_year', 'term', 'marks_status']);
            $table->dropColumn(['marks_status', 'marks_submitted_at', 'marks_submitted_by', 'marks_reviewed_at', 'marks_reviewed_by', 'marks_review_comment']);
        });
    }
};
