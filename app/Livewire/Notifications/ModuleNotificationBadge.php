<?php

namespace App\Livewire\Notifications;

use App\Services\ModuleNotificationService;
use Livewire\Component;

class ModuleNotificationBadge extends Component
{
    public string $module;

    public function render(ModuleNotificationService $notifications)
    {
        $user = auth()->user();
        $count = $notifications->count($this->module, $user?->id, $user?->hasRole('super-admin'));

        return view('livewire.notifications.module-notification-badge', compact('count'));
    }
}
