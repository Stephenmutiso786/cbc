<?php

namespace App\Livewire\SuperAdmin;

use App\Models\SystemSetting;
use Livewire\Component;

class PlatformMaintenance extends Component
{
    public bool $enabled = false;
    public string $message = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
        $this->enabled = (bool) ((int) SystemSetting::get('maintenance_mode', 0));
        $this->message = (string) SystemSetting::get('maintenance_message', config('school.maintenance_message'));
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
        $this->validate(['enabled' => ['boolean'], 'message' => ['nullable', 'string', 'max:500']]);
        SystemSetting::put('maintenance_mode', $this->enabled ? '1' : '0');
        SystemSetting::put('maintenance_message', $this->message);
        session()->flash('success', $this->enabled ? 'Platform maintenance mode is on. Only super-admins can access the platform.' : 'Platform maintenance mode is off.');
    }

    public function render()
    {
        return view('livewire.super-admin.platform-maintenance')->layout('layouts.app', ['title' => 'Platform Maintenance']);
    }
}
