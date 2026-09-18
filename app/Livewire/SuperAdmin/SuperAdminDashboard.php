<?php

namespace App\Livewire\SuperAdmin;

use App\Models\ExamResult;
use App\Models\Exam;
use App\Models\Attendance;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Guardian;
use App\Models\LearningArea;
use App\Models\SchoolClass;
use App\Models\StaffMember;
use App\Models\SupportTicket;
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
            ->withCount(['classes as active_classes_count' => fn ($query) => $query->where('is_active', true)])
            ->withCount(['users as active_users_count' => fn ($query) => $query->whereNotNull('last_seen_at')->where('last_seen_at', '>=', now()->subMinutes(10))])
            ->orderByDesc('created_at')->get();

        $smsUsedLast30Days = abs((int) SmsCreditTransaction::where('type', 'usage')->where('created_at', '>=', now()->subDays(30))->sum('amount'));
        $ingestedToday = Schema::hasTable('exam_results') ? ExamResult::whereDate('created_at', $today)->count() : 0;
        $auditLogs = SystemLog::with('user')->latest('id')->take(10)->get();
        $attendanceToday = Attendance::whereDate('date', $today);
        $attendanceTotal = (clone $attendanceToday)->count();
        $attendancePresent = (clone $attendanceToday)->whereIn('status', ['present', 'late'])->count();
        $upcomingExams = Exam::with(['school', 'schoolClass', 'learningArea'])
            ->whereDate('exam_date', '>=', $today)
            ->whereDate('exam_date', '<=', now()->addDays(14)->toDateString())
            ->orderBy('exam_date')->orderBy('start_time')->take(6)->get();
        $openTickets = SupportTicket::whereIn('status', ['open', 'investigating'])->count();
        $urgentTickets = SupportTicket::whereIn('status', ['open', 'investigating'])->where('priority', 'urgent')->count();
        $noClassSchools = $schools->where('active_classes_count', 0)->where('is_active', true);
        $expiredSchools = $schools->filter(fn (School $school) => $school->package_id && $school->packageExpired());

        $alerts = collect();
        if ($urgentTickets) $alerts->push(['level' => 'danger', 'text' => "{$urgentTickets} urgent support ticket(s) need attention.", 'route' => 'admin.support.index']);
        if ($expiredSchools->isNotEmpty()) $alerts->push(['level' => 'warning', 'text' => $expiredSchools->count() . ' school(s) have an expired subscription.', 'route' => 'admin.schools.index']);
        if ($noClassSchools->isNotEmpty()) $alerts->push(['level' => 'warning', 'text' => $noClassSchools->count() . ' active school(s) have no active classes.', 'route' => 'admin.schools.index']);
        if ($schools->where('is_locked', true)->isNotEmpty()) $alerts->push(['level' => 'neutral', 'text' => $schools->where('is_locked', true)->count() . ' school(s) are manually locked.', 'route' => 'admin.schools.index']);

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
            'totalLearners' => $schools->sum('active_learners_count'),
            'totalStaff' => StaffMember::where('is_active', true)->count(),
            'totalTeachers' => StaffMember::where('is_active', true)->where('staff_type', 'teaching')->count(),
            'totalClasses' => SchoolClass::where('is_active', true)->count(),
            'totalSubjects' => LearningArea::where('is_active', true)->count(),
            'totalGuardians' => Guardian::count(),
            'attendanceTotal' => $attendanceTotal,
            'attendanceAbsent' => (clone $attendanceToday)->where('status', 'absent')->count(),
            'attendanceRate' => $attendanceTotal ? round(($attendancePresent / $attendanceTotal) * 100, 1) : null,
            'feesCollectedToday' => (float) FeePayment::confirmed()->whereDate('paid_at', $today)->sum('amount'),
            'outstandingFees' => (float) FeeInvoice::selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as total')->value('total'),
            'newLearnersThisWeek' => \App\Models\Learner::where('is_active', true)->where('created_at', '>=', now()->subDays(7))->count(),
            'upcomingExams' => $upcomingExams,
            'openTickets' => $openTickets,
            'alerts' => $alerts,
        ])->layout('layouts.app', ['title' => 'ElimuHub Command Centre']);
    }
}
