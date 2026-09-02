<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\TeacherSubjectAllocation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ExamReportsController extends Controller
{
    public function resultCards(Exam $exam): Response
    {
        $this->authorizeExamReports($exam);
        $this->ensureGroupPublished($exam);
        $exam->load('schoolClass');
        $scale = $exam->schoolClass?->gradingScale()->first();
        $results = ExamResult::with(['learner.schoolClass', 'exam.learningArea'])
            ->whereIn('exam_id', $exam->groupExamIds())
            ->whereHas('learner')
            ->get()
            ->sortBy(fn ($result) => [$result->learner->last_name, $result->learner->first_name])
            ->values();

        abort_if($results->isEmpty(), 422, 'No marks have been entered for this exam.');

        $results = $results->groupBy('learner_id')->flatMap(function ($learnerResults) use ($scale) {
            $possible = $learnerResults->sum(fn ($result) => (float) $result->total_marks);
            $obtained = $learnerResults->sum(fn ($result) => (float) ($result->marks_obtained ?? 0));
            $percentage = $possible > 0 ? ($obtained / $possible) * 100 : 0;
            $overallGrade = $this->gradeForPercentage($percentage, $scale);
            return $learnerResults->map(function ($result) use ($percentage, $overallGrade) {
                $result->overall_percentage = round($percentage, 2);
                $result->overall_grade = $overallGrade;
                return $result;
            });
        })->values();

        return Pdf::loadView('pdf.exam-result-cards', compact('exam', 'results'))
            ->setPaper('a4', 'portrait')
            ->download('results-report-cards-' . $exam->id . '.pdf');
    }

    public function meritList(Exam $exam): Response
    {
        $this->authorizeExamReports($exam);
        $this->ensureGroupPublished($exam);
        $exam->load('schoolClass');
        $scale = $exam->schoolClass?->gradingScale()->first();
        $results = ExamResult::with(['learner.schoolClass'])
            ->whereIn('exam_id', $exam->groupExamIds())
            ->whereHas('learner')
            ->get()
            ->groupBy('learner_id')
            ->map(function ($learnerResults) use ($scale) {
                $first = $learnerResults->first();
                $first->marks_obtained = $learnerResults->sum(fn ($result) => (float) ($result->marks_obtained ?? 0));
                $first->total_marks = $learnerResults->sum(fn ($result) => (float) $result->total_marks);
                $percentage = $first->total_marks > 0
                    ? ((float) $first->marks_obtained / (float) $first->total_marks) * 100
                    : 0;
                $first->grade = $this->gradeForPercentage($percentage, $scale);
                $first->rubric_level = null;
                $first->remarks = 'Combined results across ' . $learnerResults->count() . ' learning areas.';
                return $first;
            })
            ->sortByDesc(fn ($result) => $result->total_marks > 0 ? (float) $result->marks_obtained / (float) $result->total_marks : 0)
            ->values();

        abort_if($results->isEmpty(), 422, 'No marks have been entered for this exam.');

        $position = 0;
        $previousPercentage = null;
        $ranked = $results->map(function ($result, $index) use (&$position, &$previousPercentage) {
            $percentage = $result->total_marks > 0
                ? round((float) $result->marks_obtained / (float) $result->total_marks * 100, 1)
                : 0;
            if ($previousPercentage === null || $percentage < $previousPercentage) {
                $position = $index + 1;
            }
            $previousPercentage = $percentage;
            $result->merit_position = $position;
            $result->merit_percentage = $percentage;
            return $result;
        });

        return Pdf::loadView('pdf.exam-merit-list', ['exam' => $exam, 'results' => $ranked])
            ->setPaper('a4', 'portrait')
            ->download('merit-list-' . $exam->id . '.pdf');
    }

    private function gradeForPercentage(float $percentage, $scale): string
    {
        $percentage = max(0, min(100, $percentage));
        if ($scale && ($grade = $scale->gradeForPercent($percentage)) !== null) {
            return $grade;
        }

        return match (true) {
            $percentage >= 80 => 'A',
            $percentage >= 70 => 'B',
            $percentage >= 60 => 'C',
            $percentage >= 50 => 'D',
            default => 'E',
        };
    }

    private function authorizeExamReports(Exam $exam): void
    {
        if (auth()->user()->can('manage exams') || auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher'])) {
            return;
        }

        $staff = auth()->user()->staffMember;
        abort_unless($staff, 403);
        $examIds = $exam->groupExamIds();
        $classIds = $staff->classes()->pluck('id');
        $isClassTeacher = Exam::whereIn('id', $examIds)->whereIn('class_id', $classIds)->exists();
        $areaIds = Exam::whereIn('id', $examIds)->pluck('learning_area_id');
        $isAllocatedTeacher = TeacherSubjectAllocation::where('teacher_id', $staff->id)
            ->whereIn('class_id', $classIds)->whereIn('learning_area_id', $areaIds)
            ->where('academic_year', config('school.academic_year'))->where('is_active', true)->exists();

        abort_unless($isClassTeacher || $isAllocatedTeacher, 403);
    }

    private function ensureGroupPublished(Exam $exam): void
    {
        $group = Exam::whereIn('id', $exam->groupExamIds())->get();
        abort_unless($group->isNotEmpty() && $group->every(fn ($item) => $item->exam_state === 'published' && $item->status === 'published' && $item->marks_status === 'approved'), 422, 'Results are not published. The complete exam must be finalized and published first.');
    }
}
