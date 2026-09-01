<?php

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $year = AcademicYear::updateOrCreate(
                ['year' => 2026],
                ['starts_on' => '2026-01-05', 'ends_on' => '2026-10-23', 'is_active' => true],
            );
            $terms = [
                1 => ['Term 1', '2026-01-05', '2026-04-02'],
                2 => ['Term 2', '2026-04-27', '2026-07-31'],
                3 => ['Term 3', '2026-08-24', '2026-10-23'],
            ];
            foreach ($terms as $number => [$name, $starts, $ends]) {
                AcademicTerm::updateOrCreate(
                    ['academic_year_id' => $year->id, 'number' => $number],
                    ['name' => $name, 'starts_on' => $starts, 'ends_on' => $ends, 'is_active' => $number === 1],
                );
            }
            AcademicYear::whereKey($year->id)->update(['is_active' => true]);
            AcademicYear::where('id', '!=', $year->id)->update(['is_active' => false]);
            SchoolSetting::updateOrCreate(['key' => 'academic_year'], ['value' => '2026']);
            SchoolSetting::updateOrCreate(['key' => 'current_term'], ['value' => '1']);
        });
    }

    public function down(): void
    {
        // Keep the school calendar available if this deployment is rolled back.
    }
};
