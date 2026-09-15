<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\SchoolClass;
use App\Services\GoogleDriveStorage;
use Database\Seeders\DefaultClassSubjectsSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DriveStoreController extends Controller
{
    public function index(Request $request, GoogleDriveStorage $drive): View
    {
        $files = [];
        $error = null;
        $snapshot = null;
        $snapshotError = null;

        try {
            $files = $drive->listFiles();
            if ($request->filled('snapshot')) {
                $file = collect($files)->firstWhere('id', $request->string('snapshot')->toString());
                if (! $file) {
                    $snapshotError = 'The selected Drive file is no longer available.';
                } else {
                    $snapshot = $this->readSnapshot($drive, $file['id']);
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
            if ($request->filled('snapshot')) $snapshotError = $exception->getMessage();
            else $error = $exception->getMessage();
        }

        return view('admin.drive-store', compact('files', 'error', 'snapshot', 'snapshotError'));
    }

    public function syncRecords(): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage system settings'), 403);

        try {
            $exitCode = Artisan::call('records:drive');
            $output = trim(Artisan::output());
            if ($exitCode !== 0) {
                return redirect()->route('admin.drive-store.index')->withErrors(['drive' => $output ?: 'The class records could not be synced to Google Drive.']);
            }

            return redirect()->route('admin.drive-store.index')->with('success', $output ?: 'Class records synced to Google Drive.');
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->route('admin.drive-store.index')->withErrors(['drive' => $exception->getMessage()]);
        }
    }

    /** Import a snapshot made by the records:drive command into this database. */
    public function importRecords(Request $request, GoogleDriveStorage $drive): RedirectResponse
    {
        abort_unless(auth()->user()->can('create students'), 403);
        $data = $request->validate(['file_id' => ['required', 'string', 'max:255']]);

        try {
            $file = collect($drive->listFiles())->firstWhere('id', $data['file_id']);
            abort_unless($file, 404, 'The selected Drive file is no longer available.');
            $snapshot = $this->readSnapshot($drive, $file['id']);
            $imported = 0;
            $skipped = 0;
            $classesCreated = 0;

            DB::transaction(function () use ($snapshot, &$imported, &$skipped, &$classesCreated): void {
                foreach ($snapshot['classes'] as $record) {
                    $grade = (string) ($record['grade_level'] ?? '');
                    $name = trim((string) ($record['name'] ?? ''));
                    if (!in_array($grade, array_merge(...array_values(config('school.grade_levels'))), true) || $name === '') {
                        throw new \RuntimeException('The Drive snapshot contains an invalid class record.');
                    }
                    $class = SchoolClass::firstOrCreate(
                        ['grade_level' => $grade, 'name' => $name, 'academic_year' => (string) ($record['academic_year'] ?? config('school.academic_year'))],
                        ['stream' => $record['stream'] ?? null, 'capacity' => 45, 'is_active' => true],
                    );
                    if ($class->wasRecentlyCreated) $classesCreated++;
                    app(DefaultClassSubjectsSeeder::class)->seedForClass($class);

                    foreach (($record['learners'] ?? []) as $learner) {
                        $admission = trim((string) ($learner['admission_number'] ?? ''));
                        $first = trim((string) ($learner['first_name'] ?? ''));
                        $last = trim((string) ($learner['last_name'] ?? ''));
                        if ($admission === '' || $first === '' || $last === '') {
                            $skipped++;
                            continue;
                        }
                        if (Learner::withTrashed()->where('admission_number', $admission)->exists()) {
                            $skipped++;
                            continue;
                        }
                        Learner::create([
                            'admission_number' => $admission,
                            'first_name' => $first,
                            'middle_name' => trim((string) ($learner['middle_name'] ?? '')) ?: null,
                            'last_name' => $last,
                            'gender' => in_array($learner['gender'] ?? null, ['male', 'female'], true) ? $learner['gender'] : 'male',
                            'grade_level' => $grade,
                            'class_id' => $class->id,
                            'stream' => $learner['stream'] ?? $record['stream'] ?? null,
                            'admission_date' => now()->toDateString(),
                            'boarding_status' => in_array($learner['boarding_status'] ?? null, ['day', 'boarding'], true) ? $learner['boarding_status'] : 'day',
                            'academic_year' => (string) ($learner['academic_year'] ?? $record['academic_year'] ?? config('school.academic_year')),
                            'is_active' => (bool) ($learner['is_active'] ?? true),
                        ]);
                        $imported++;
                    }
                }
            });

            return redirect()->route('admin.drive-store.index')->with('success', "Imported {$imported} learner(s) from Drive. {$skipped} existing or incomplete row(s) were skipped; {$classesCreated} class(es) were created. Subjects were configured for every imported class.");
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->route('admin.drive-store.index')->withErrors(['drive' => 'Drive import failed: ' . $exception->getMessage()]);
        }
    }

    public function repairClassSubjects(): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage curriculum'), 403);
        app(DefaultClassSubjectsSeeder::class)->run();
        $unconfigured = SchoolClass::forConfiguredGrades()->whereDoesntHave('learningAreas')->count();

        return redirect()->route('admin.drive-store.index')->with('success', $unconfigured === 0
            ? 'Subjects are now configured for every class.'
            : "Subject repair completed, but {$unconfigured} class(es) still need review.");
    }

    private function readSnapshot(GoogleDriveStorage $drive, string $fileId): array
    {
        $contents = $drive->contents('gdrive:' . $fileId);
        // Drive IDs have no extension; snapshots are gzip encoded, while a
        // plain JSON snapshot is accepted for a manually restored backup.
        $snapshot = json_decode($contents, true);
        if (!is_array($snapshot)) $snapshot = json_decode(gzdecode($contents) ?: '', true);
        if (!is_array($snapshot) || ($snapshot['format'] ?? null) !== 'cbc-school-records-v1' || !is_array($snapshot['classes'] ?? null)) {
            throw new \RuntimeException('This is not a valid CBC school-records snapshot. Use the school-records-YYYY.json.gz file created by Sync class records now.');
        }
        return $snapshot;
    }
}
