<?php

namespace App\Console\Commands;

use App\Models\SchoolClass;
use App\Models\StaffMember;
use App\Services\DataTransferPolicy;
use App\Services\GoogleDriveStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
        $classes = SchoolClass::query()
            ->with(['learners:id,class_id,admission_number,first_name,middle_name,last_name,grade_level,stream,academic_year,is_active'])
            ->orderBy('grade_level')->orderBy('name')->get();

        foreach ($classes as $class) {
            $stream = fopen('php://temp', 'w+');
            if ($stream === false) {
                throw new \RuntimeException('A class list could not be prepared.');
            }
            fputcsv($stream, ['No.', 'Admission number', 'Learner name', 'Grade', 'Stream', 'Academic year', 'Status']);
            foreach ($class->learners->sortBy([['last_name', 'asc'], ['first_name', 'asc']])->values() as $number => $learner) {
                fputcsv($stream, [
                    $number + 1,
                    $learner->admission_number,
                    $learner->full_name,
                    (string) $learner->grade_level,
                    $learner->stream,
                    $learner->academic_year,
                    $learner->is_active ? 'Active' : 'Inactive',
                ]);
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            fclose($stream);
            if (! is_string($csv)) {
                throw new \RuntimeException('A class list could not be read.');
            }

            $fileName = 'class-list-' . $year . '-' . Str::slug((string) $class->grade_level . '-' . $class->name) . '.csv';
            $drive->storeOrReplace($csv, 'records/classes', $fileName, 'text/csv');
        }

        $snapshot = [
            'format' => 'cbc-school-records-v1',
            'generated_at' => now()->toIso8601String(),
            'academic_year' => $year,
            'current_term' => (string) config('school.current_term'),
            'classes' => $classes
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
