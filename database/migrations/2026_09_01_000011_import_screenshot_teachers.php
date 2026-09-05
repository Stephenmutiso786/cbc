<?php

use Database\Seeders\ScreenshotTeachersSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // This also runs on an existing production database, not only on a fresh install.
        // Migrations run before DatabaseSeeder, so create the role required by the import.
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        app(ScreenshotTeachersSeeder::class)->run();
    }

    public function down(): void
    {
        // Keep imported staff records intact when rolling back unrelated migrations.
    }
};
