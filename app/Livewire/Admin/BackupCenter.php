<?php

namespace App\Livewire\Admin;

use App\Models\DatabaseBackup;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\WithPagination;

class BackupCenter extends Component
{
    use WithPagination;

    public string $notice = '';
    public string $error = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage system settings'), 403);
    }

    public function runBackup(): void
    {
        abort_unless(auth()->user()->can('manage system settings'), 403);
        $this->notice = '';
        $this->error = '';

        try {
            $exitCode = Artisan::call('backup:drive', ['--force' => true]);
            $output = trim(Artisan::output());
            if ($exitCode !== 0) {
                throw new \RuntimeException($output ?: 'The backup command failed.');
            }
            $this->notice = $output ?: 'Backup completed successfully.';
        } catch (\Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.admin.backup-center', [
            'backups' => DatabaseBackup::query()->latest('id')->paginate(20),
        ])->layout('layouts.admin');
    }
}
