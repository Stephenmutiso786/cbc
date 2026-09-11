<?php

use Database\Seeders\DefaultClassSubjectsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Attach the configured CBE learning areas to classes that already existed
     * when the initial subject-seeding migration ran.  The seeder uses
     * updateOrCreate/syncWithoutDetaching, so this safely repairs a live
     * database without duplicating subjects or removing school customisations.
     */
    public function up(): void
    {
        app(DefaultClassSubjectsSeeder::class)->run();
    }

    public function down(): void
    {
        // Class-to-subject assignments are school configuration. Never remove
        // them automatically during a rollback.
    }
};
