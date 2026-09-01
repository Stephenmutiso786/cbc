<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SchoolClassesSeeder::class,
            DefaultGradingScalesSeeder::class,
            DefaultClassSubjectsSeeder::class,
            AdminUserSeeder::class,
            ScreenshotTeachersSeeder::class,
        ]);
    }
}
