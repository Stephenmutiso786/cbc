<?php

namespace App\Livewire\SuperAdmin;

use App\Models\NotificationLog;
use App\Models\School;
use App\Models\SmsCreditOrder;
use App\Models\SmsCreditTransaction;
use App\Services\SmsCapacityService;
use Livewire\Component;

/** Platform-only view of the single shared Olympus SMS account. */
class SmsControlCenter extends Component
{
    public string $notice = '';
    public string $error = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    }

    /** Refresh the provider balance and release every paid order it can cover. */
    public function refreshCapacity(SmsCapacityService $capacity): void
    {
        $this->notice = '';
        $this->error = '';

        try {
            $balance = $capacity->refreshProviderBalance();
            $fulfilled = $capacity->fulfilHeldOrders();
            $this->notice = "Olympus balance refreshed: " . number_format($balance) . " SMS." . ($fulfilled ? " {$fulfilled} held order(s) were allocated." : '');
        } catch (\Throwable $exception) {
            report($exception);
            $this->error = 'The live Olympus balance could not be refreshed. Check the platform SMS connection in Platform Settings.';
        }
    }

    public function render(SmsCapacityService $capacity)
    {
        $summary = $capacity->summary();
        $orders = SmsCreditOrder::withoutSchoolScope()->with('school')->latest()->limit(10)->get();
        $wallets = School::query()->orderByDesc('sms_credits')->limit(10)->get();
        $dailyUsage = SmsCreditTransaction::query()
            ->where('type', 'usage')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as day, ABS(SUM(amount)) as units')
            ->groupBy('day')->orderBy('day')->get();
        $delivery = NotificationLog::query()
            ->where('channel', 'sms')->where('created_at', '>=', now()->startOfDay())
            ->selectRaw("status, COUNT(*) as total")->groupBy('status')->pluck('total', 'status');

        return view('livewire.super-admin.sms-control-center', compact('summary', 'orders', 'wallets', 'dailyUsage', 'delivery'))
            ->layout('layouts.admin');
    }
}
