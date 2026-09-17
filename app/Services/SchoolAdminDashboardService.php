<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamResult;
use Illuminate\Support\Facades\Schema;

/** Small, safe, school-scoped metrics for the school administrator portal. */
class SchoolAdminDashboardService
{
    public function summary(): array
    {
        $empty = [
            'attendance_rate' => null,
            'attendance_total' => 0,
            'latest_exam' => null,
            'exam_mean' => null,
            'marks_entered' => 0,
        ];

        try {
            if (! Schema::hasTable('attendance') || ! Schema::hasTable('exams') || ! Schema::hasTable('exam_results')) {
                return $empty;
            }

            $attendance = Attendance::whereDate('date', today());
            $attendanceTotal = (clone $attendance)->count();
            $present = (clone $attendance)->where('status', 'present')->count();

            $exam = Exam::query()
                ->where('academic_year', (string) config('school.academic_year'))
                ->where('term', config('school.current_term'))
                ->latest('exam_date')
                ->latest('id')
                ->first();

            $resultQuery = $exam
                ? ExamResult::whereIn('exam_id', $exam->groupExamIds())->whereNotNull('marks_obtained')->where('total_marks', '>', 0)
                : null;

            return [
                'attendance_rate' => $attendanceTotal ? round($present / $attendanceTotal * 100, 1) : null,
                'attendance_total' => $attendanceTotal,
                'latest_exam' => $exam,
                'exam_mean' => $resultQuery ? round((float) (clone $resultQuery)->selectRaw('AVG(marks_obtained / total_marks * 100) AS average')->value('average'), 1) : null,
                'marks_entered' => $resultQuery ? (clone $resultQuery)->count() : 0,
            ];
        } catch (\Throwable $exception) {
            report($exception);
            return $empty;
        }
    }
}
