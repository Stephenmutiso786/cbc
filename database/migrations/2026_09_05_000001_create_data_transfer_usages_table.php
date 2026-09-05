<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('data_transfer_usages')) return;

        Schema::create('data_transfer_usages', function (Blueprint $table): void {
            $table->id();
            $table->date('usage_date')->unique();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_transfer_usages');
    }
};
