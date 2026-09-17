<?php

namespace App\Livewire\SuperAdmin;

use App\Models\ExamResult;
use App\Models\School;
use App\Models\SmsCreditTransaction;
use App\Models\SubscriptionPayment;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/** Platform-only command centre. It must never query a school's portal data. */
class SuperAdminDashboard extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    }

    public function render()
    {
        $today = now()->toDateString();
        $schools = School::query()->with('package')
            ->withCount(['learners as active_learners_count' => fn ($query) => $query->where('is_active', true)])
            ->withCount(['users as active_users_count' => fn ($query) => $query->whereNotNull('last_seen_at')->where('last_seen_at', '>=', now()->subMinutes(10))])
            ->orderByDesc('created_at')->get();

        $smsUsedLast30Days = abs((int) SmsCreditTransaction::where('type', 'usage')->where('created_at', '>=', now()->subDays(30))->sum('amount'));
        $ingestedToday = Schema::hasTable('exam_results') ? ExamResult::whereDate('created_at', $today)->count() : 0;
        $auditLogs = SystemLog::with('user')->latest('id')->take(10)->get();

        return view('livewire.super-admin.super-admin-dashboard', [
            'schools' => $schools->take(8),
            'totalSchools' => $schools->count(),
            'activeSchools' => $schools->where('is_active', true)->count(),
            'activeSubscriptions' => $schools->filter(fn (School $school) => $school->isOnActiveSubscription())->count(),
            'expiredSubscriptions' => $schools->filter(fn (School $school) => $school->package_id && $school->packageExpired())->count(),
            'subscriptionRevenue' => (float) SubscriptionPayment::where('status', 'confirmed')->where('created_at', '>=', now()->subDays(30))->sum('amount'),
            'pendingPayments' => SubscriptionPayment::whereIn('status', ['pending', 'initiated'])->count(),
            'verifiedSmsAllocations' => SmsCreditTransaction::where('type', 'topup')->whereNotNull('amount_paid')->whereNotNull('payment_reference')->count(),
            'smsUsedLast30Days' => $smsUsedLast30Days,
            'onlineSchools' => $schools->filter(fn (School $school) => $school->active_users_count > 0)->count(),
            'ingestedToday' => $ingestedToday,
            'auditLogs' => $auditLogs,
        ])->layout('layouts.app', ['title' => 'ElimuHub Command Centre']);
    }
}
