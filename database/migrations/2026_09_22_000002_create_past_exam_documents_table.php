<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('past_exam_documents', function (Blueprint $table) { $table->id(); $table->foreignId('school_id')->constrained()->cascadeOnDelete(); $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete(); $table->string('title'); $table->string('document_type', 30); $table->string('academic_year', 9)->nullable(); $table->string('term', 20)->nullable(); $table->string('file_path'); $table->string('original_name'); $table->unsignedBigInteger('file_size'); $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['school_id', 'document_type']); }); } public function down(): void { Schema::dropIfExists('past_exam_documents'); } };
