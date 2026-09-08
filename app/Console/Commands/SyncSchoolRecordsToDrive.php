<?php

namespace App\Console\Commands;

use App\Models\SchoolClass;
use App\Models\StaffMember;
use App\Services\DataTransferPolicy;
use App\Services\GoogleDriveStorage;
use Illuminate\Console\Command;

class SyncSchoolRecordsToDrive extends Command
{
    protected $signature = 'records:drive';

    protected $description = 'Sync a compressed, readable class and staff record snapshot to Google Drive';

    public function handle(GoogleDriveStorage $drive, DataTransferPolicy $transferPolicy): int
    {
        if (! $drive->enabled()) {
            $this->error('Google Drive is not configured. Connect Drive before syncing school records.');
            return self::FAILURE;
        }

        $year = (string) config('school.academic_year');
        $snapshot = [
            'format' => 'cbc-school-records-v1',
            'generated_at' => now()->toIso8601String(),
            'academic_year' => $year,
            'current_term' => (string) config('school.current_term'),
            'classes' => SchoolClass::query()
                ->with(['learners:id,class_id,admission_number,first_name,middle_name,last_name,grade_level,stream,academic_year,is_active'])
                ->orderBy('grade_level')->orderBy('name')->get()
                ->map(fn (SchoolClass $class): array => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'grade_level' => $class->grade_level,
                    'stream' => $class->stream,
                    'academic_year' => $class->academic_year,
                    'is_active' => $class->is_active,
                    'learners' => $class->learners->map(fn ($learner): array => $learner->only([
                        'id', 'admission_number', 'first_name', 'middle_name', 'last_name',
                        'grade_level', 'stream', 'academic_year', 'is_active',
                    ]))->values()->all(),
                ])->values()->all(),
            'staff' => StaffMember::query()
                ->select(['id', 'user_id', 'staff_number', 'first_name', 'last_name', 'employment_type', 'is_active'])
                ->with('user:id,name,email')->orderBy('last_name')->get()
                ->map(fn (StaffMember $staff): array => [
                    'id' => $staff->id,
                    'staff_number' => $staff->staff_number,
                    'name' => trim($staff->first_name . ' ' . $staff->last_name),
                    'employment_type' => $staff->employment_type,
                    'is_active' => $staff->is_active,
                    'account' => $staff->user?->only(['id', 'name', 'email']),
                ])->values()->all(),
        ];

        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $compressed = gzencode($json, 9);
        if ($compressed === false) {
            $this->error('The school records snapshot could not be compressed.');
            return self::FAILURE;
        }

        $path = $drive->storeOrReplace($compressed, 'records/classes', 'school-records-' . $year . '.json.gz', 'application/gzip');
        $learnerCount = collect($snapshot['classes'])->sum(fn (array $class): int => count($class['learners']));
        $this->info("Synced {$learnerCount} learners in " . count($snapshot['classes']) . ' classes to Google Drive (' . $transferPolicy->formatBytes(strlen($compressed)) . ').');
        $this->line('Drive reference: ' . $path);

        return self::SUCCESS;
    }
}
