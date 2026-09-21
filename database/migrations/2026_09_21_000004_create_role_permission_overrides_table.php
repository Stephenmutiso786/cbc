<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('role_permission_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained('roles')->cascadeOnDelete();
            $table->json('permission_ids');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_overrides');
    }
};
