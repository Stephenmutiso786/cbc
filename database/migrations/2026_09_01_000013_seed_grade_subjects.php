<?php

use Database\Seeders\DefaultClassSubjectsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(DefaultClassSubjectsSeeder::class)->run();
    }

    public function down(): void
    {
        // Subject assignments are school configuration and are not removed automatically.
    }
};
