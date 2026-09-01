<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 20);
            $table->unsignedBigInteger('database_size_bytes');
            $table->unsignedBigInteger('archive_size_bytes')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backups');
    }
};
