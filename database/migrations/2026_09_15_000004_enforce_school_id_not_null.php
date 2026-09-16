<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (\Illuminate\Support\Facades\Schema::getTables() as $tableInfo) {
            $table = $tableInfo['name'];
            if ($table === 'users' || !Schema::hasColumn($table, 'school_id')) continue;
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN school_id SET NOT NULL");
            elseif ($driver === 'mysql') {
                // MySQL refuses to alter a column while its FK exists. Re-add
                // the same restrictive FK immediately after making it NOT NULL.
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_school_id_foreign`");
                DB::statement("ALTER TABLE `{$table}` MODIFY school_id BIGINT UNSIGNED NOT NULL");
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE RESTRICT");
            }
        }
    }
    public function down(): void
    {
        foreach (Schema::getTables() as $tableInfo) {
            $table = $tableInfo['name'];
            if ($table === 'users' || !Schema::hasColumn($table, 'school_id')) continue;
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN school_id DROP NOT NULL");
            elseif ($driver === 'mysql') {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_school_id_foreign`");
                DB::statement("ALTER TABLE `{$table}` MODIFY school_id BIGINT UNSIGNED NULL");
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE RESTRICT");
            }
        }
    }
};
