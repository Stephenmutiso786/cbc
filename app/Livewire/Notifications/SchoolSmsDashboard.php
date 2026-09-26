<?php

namespace App\Livewire\Notifications;

use App\Models\School;
use App\Models\SchoolNotification;
use App\Models\SmsCreditOrder;
use App\Models\SmsCreditTransaction;
use Livewire\Component;

/** A tenant-safe SMS wallet page; no provider credentials or cross-school data. */
class SchoolSmsDashboard extends Component
{
    public function mount(): void
    {
        if (auth()->user()?->hasRole('super-admin')) {
            $this->redirectRoute('admin.sms-control.index');
            return;
        }
        abort_unless(auth()->user()?->school_id, 403);
    }

    public function render()
    {
        $schoolId = (int) auth()->user()->school_id;
        $school = School::withoutGlobalScopes()->findOrFail($schoolId);
        $transactions = SmsCreditTransaction::query()->where('school_id', $schoolId);
        $purchased = (int) (clone $transactions)->where('type', 'topup')->sum('amount');
        $used = abs((int) (clone $transactions)->where('type', 'usage')->sum('amount'));
        $spent = (float) (clone $transactions)->where('type', 'topup')->sum('amount_paid');
        $recentOrders = SmsCreditOrder::query()->latest()->limit(8)->get();
        $recentMessages = SchoolNotification::query()->where('channel', 'sms')->latest()->limit(8)->get();
        $dailyUsage = (clone $transactions)->where('type', 'usage')->where('created_at', '>=', now()->subDays(6)->startOfDay())->selectRaw('DATE(created_at) as day, ABS(SUM(amount)) as units')->groupBy('day')->orderBy('day')->get();

        return view('livewire.notifications.school-sms-dashboard', compact('school', 'purchased', 'used', 'spent', 'recentOrders', 'recentMessages', 'dailyUsage'))
            ->layout('layouts.admin');
    }
}
