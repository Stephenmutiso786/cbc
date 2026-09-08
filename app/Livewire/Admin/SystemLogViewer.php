<?php

namespace App\Livewire\Admin;

use App\Models\SystemLog;
use Livewire\Component;
use Livewire\WithPagination;

class SystemLogViewer extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void { abort_unless(auth()->user()->hasRole('super-admin'), 403); }
    public function updatingSearch(): void { $this->resetPage(); }

    public function render()
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);
        $logs = SystemLog::with('user')->when(trim($this->search) !== '', function ($query): void {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($nested) use ($term): void {
                $nested->where('path', 'like', $term)
                    ->orWhere('method', 'like', $term)
                    ->orWhere('status', 'like', $term);
            });
        })->latest('id')->paginate(40);

        return view('livewire.admin.system-log-viewer', compact('logs'))->layout('layouts.admin');
    }
}
