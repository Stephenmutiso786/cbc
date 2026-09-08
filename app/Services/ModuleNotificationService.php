<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\DatabaseBackup;
use App\Models\Exam;
use App\Models\FeePayment;
use App\Models\InventoryItem;
use App\Models\Learner;
use App\Models\LearnerPromotion;
use App\Models\LearningArea;
use App\Models\LearningNote;
use App\Models\SchoolClass;
use App\Models\SchoolNotification;
use App\Models\StaffMember;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Carbon;

class ModuleNotificationService
{
    public function count(string $module, ?int $userId = null, bool $isSuperAdmin = false): int
    {
        try {
            $since = Carbon::now()->subDay();

            return match ($module) {
                'learners' => Learner::where('created_at', '>=', $since)->count(),
                'staff' => StaffMember::where('created_at', '>=', $since)->count(),
                'classes' => SchoolClass::where('created_at', '>=', $since)->count(),
                'subjects' => LearningArea::where('created_at', '>=', $since)->count(),
                'assessments' => Assessment::where('created_at', '>=', $since)->count(),
                'exams' => Exam::where('created_at', '>=', $since)->count(),
                'notes' => LearningNote::where('created_at', '>=', $since)->count(),
                'payments' => FeePayment::where('created_at', '>=', $since)->count(),
                'inventory' => InventoryItem::where('created_at', '>=', $since)->count(),
                'notifications' => $this->notificationsFor($userId)
                    ->where('created_at', '>=', $since)
                    ->count(),
                'support' => SupportTicket::query()
                    ->when(! $isSuperAdmin, fn ($query) => $query->where('created_by', $userId))
                    ->where('status', 'open')
                    ->count(),
                'backups' => DatabaseBackup::where('created_at', '>=', $since)
                    ->where('status', 'failed')
                    ->count(),
                'promotions' => LearnerPromotion::where('status', 'pending')->count(),
                'users' => User::where('created_at', '>=', $since)->count(),
                default => 0,
            };
        } catch (\Throwable) {
            // A missing table during first deployment must not break navigation.
            return 0;
        }
    }

    public function notificationsFor(?int $userId)
    {
        $query = SchoolNotification::query()->whereIn('status', ['queued', 'sent', 'partial']);
        $user = $userId ? User::with('guardian.learners')->find($userId) : null;
        $guardian = $user?->guardian;

        if (! $guardian) {
            return $query;
        }

        $learners = $guardian->learners->where('is_active', true);
        $grades = $learners->pluck('grade_level')->filter()->unique()->values();
        $classIds = $learners->pluck('class_id')->filter()->unique()->values();
        $hasBoarding = $learners->contains(fn ($learner) => $learner->boarding_status === 'boarding');
        $hasDay = $learners->contains(fn ($learner) => $learner->boarding_status === 'day');

        return $query->where(function ($notifications) use ($grades, $classIds, $hasBoarding, $hasDay): void {
            $notifications->where(function ($general): void {
                $general->where('target_group', 'all')
                    ->whereNull('target_grade')
                    ->whereNull('target_class_id');
            });

            if ($grades->isNotEmpty()) {
                $notifications->orWhereIn('target_grade', $grades);
            }
            if ($classIds->isNotEmpty()) {
                $notifications->orWhereIn('target_class_id', $classIds);
            }
            if ($hasBoarding) {
                $notifications->orWhere('target_group', 'boarding');
            }
            if ($hasDay) {
                $notifications->orWhere('target_group', 'day');
            }
        });
    }

    public function moduleForRoute(string $route): ?string
    {
        return match (true) {
            str_contains($route, 'students') || str_contains($route, 'learners') => 'learners',
            str_contains($route, 'staff') => 'staff',
            str_contains($route, 'classes') => 'classes',
            str_contains($route, 'subjects') => 'subjects',
            str_contains($route, 'assessment') => 'assessments',
            str_contains($route, 'exams') => 'exams',
            str_contains($route, 'notes') => 'notes',
            str_contains($route, 'payments') || str_contains($route, 'invoices') => 'payments',
            str_contains($route, 'inventory') => 'inventory',
            str_contains($route, 'notifications') || str_contains($route, 'sms') => 'notifications',
            str_contains($route, 'support') => 'support',
            str_contains($route, 'backups') => 'backups',
            str_contains($route, 'promotions') => 'promotions',
            str_contains($route, 'user-accounts') => 'users',
            default => null,
        };
    }
}
