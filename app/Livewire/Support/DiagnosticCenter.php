<?php

namespace App\Livewire\Support;

use App\Models\SupportTicket;
use App\Models\DatabaseBackup;
use App\Services\GoogleDriveStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class DiagnosticCenter extends Component
{
    public array $checks = [];

    public function mount(): void { $this->scan(); }

    public function scan(): void
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);
        $this->checks = [];
        $this->check('Database connection', fn () => DB::select('select 1') ? 'Database is responding.' : 'No response.');
        $this->check('Migrations table', fn () => Schema::hasTable('migrations') ? 'Migrations table is available.' : 'Migrations table is missing.');
        $this->check('Storage writable', fn () => Storage::disk('local')->put('diagnostics/.keep', 'ok') ? 'Local storage is writable.' : 'Storage write failed.');
        $this->check('Google Drive', function (): string {
            $drive = app(GoogleDriveStorage::class);
            return $drive->enabled() ? 'Drive is configured.' : 'Drive is not configured or not connected.';
        });
        $this->check('Application key', fn () => (string) config('app.key') !== '' ? 'Application key is configured.' : 'Application key is missing.');
        $this->check('Current academic period', fn () => 'Year ' . config('school.academic_year') . ', Term ' . config('school.current_term') . '.');
        $this->check('Open support tickets', fn () => SupportTicket::whereIn('status', ['open', 'investigating'])->count() . ' ticket(s) require attention.');
        $this->check('Failed queued jobs', function (): string {
            if (! Schema::hasTable('failed_jobs')) {
                return 'Failed-jobs table is not configured.';
            }

            $count = DB::table('failed_jobs')->count();
            return $count === 0 ? 'No failed queued jobs.' : $count . ' failed queued job(s) require attention.';
        });
        $this->check('Latest Drive backup', function (): string {
            $backup = DatabaseBackup::query()->latest('id')->first();
            if (! $backup) {
                return 'No database backup has been recorded yet.';
            }

            return 'Latest backup is ' . $backup->status . ' (' . $backup->created_at?->toDateTimeString() . ').';
        });
    }

    private function check(string $name, callable $callback): void
    {
        try {
            $this->checks[] = ['name' => $name, 'status' => 'ok', 'message' => $callback()];
        } catch (\Throwable $exception) {
            report($exception);
            $this->checks[] = ['name' => $name, 'status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    public function render()
    {
        return view('livewire.support.diagnostic-center')->layout('layouts.admin');
    }
}
