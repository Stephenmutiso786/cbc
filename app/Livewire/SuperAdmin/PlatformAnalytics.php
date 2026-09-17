<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Attendance;
use App\Models\ExamResult;
use App\Models\School;
use App\Support\Tenant;
use Livewire\Component;

class PlatformAnalytics extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    }

    public function render()
    {
        $since = now()->subDays(30);

        $rows = School::query()
            ->withCount(['learners as active_learners_count' => fn ($q) => $q->where('is_active', true)])
            ->withCount(['staffMembers as active_staff_count' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->map(function (School $school) use ($since) {
                [$present, $total] = Tenant::run($school->id, function () use ($since) {
                    $total = Attendance::where('date', '>=', $since)->count();
                    $present = Attendance::where('date', '>=', $since)->where('status', 'present')->count();
                    return [$present, $total];
                });

                $avgScore = Tenant::run($school->id, fn () => ExamResult::where('created_at', '>=', $since)
                    ->whereNotNull('marks_obtained')->where('total_marks', '>', 0)
                    ->selectRaw('AVG(marks_obtained / total_marks * 100) as avg_pct')
                    ->value('avg_pct'));

                return [
                    'school' => $school,
                    'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : null,
                    'avg_score' => $avgScore !== null ? round((float) $avgScore, 1) : null,
                ];
            })
            ->sortByDesc(fn ($row) => $row['school']->active_learners_count)
            ->values();

        return view('livewire.super-admin.platform-analytics', [
            'rows' => $rows,
            'totalLearners' => $rows->sum(fn ($r) => $r['school']->active_learners_count),
            'totalStaff' => $rows->sum(fn ($r) => $r['school']->active_staff_count),
            'totalSchools' => $rows->count(),
        ])->layout('layouts.admin');
    }
}

