<?php

namespace App\Livewire\Notifications;

use App\Services\ModuleNotificationService;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationInbox extends Component
{
    use WithPagination;

    public function render(ModuleNotificationService $notificationService)
    {
        $notifications = $notificationService->notificationsFor(auth()->id())
            ->latest()
            ->paginate(15);

        $layout = request()->routeIs('parent.*') ? 'layouts.parent' : 'layouts.finance';

        return view('livewire.notifications.notification-inbox', compact('notifications'))
            ->layout($layout);
    }
}
