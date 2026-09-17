<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schools', 'school_code')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->string('school_code', 20)->nullable()->after('slug');
            });
        }

        DB::table('schools')->whereNull('school_code')->orderBy('id')->each(function (object $school): void {
            $words = preg_split('/[^[:alnum:]]+/u', trim((string) $school->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $base = Str::upper(collect($words)->map(fn (string $word) => Str::substr($word, 0, 1))->implode('')) ?: 'SCHOOL';
            $base = Str::limit($base, 10, '');
            DB::table('schools')->where('id', $school->id)->update(['school_code' => $base . '-' . $school->id, 'updated_at' => now()]);
        });

        Schema::table('schools', function (Blueprint $table): void {
            $table->unique('school_code', 'schools_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropUnique('schools_school_code_unique');
            $table->dropColumn('school_code');
        });
    }
};
