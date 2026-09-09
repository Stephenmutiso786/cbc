<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        app(\App\Support\RolePermissionSynchronizer::class)->synchronize();
    }

    public function down(): void {}
};
