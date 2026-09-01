<?php

use Database\Seeders\ScreenshotTeachersSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // This also runs on an existing production database, not only on a fresh install.
        app(ScreenshotTeachersSeeder::class)->run();
    }

    public function down(): void
    {
        // Keep imported staff records intact when rolling back unrelated migrations.
    }
};
