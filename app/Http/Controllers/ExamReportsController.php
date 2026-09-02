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
        $results = ExamResult::with(['learner.schoolClass', 'exam.learningArea'])
            ->whereIn('exam_id', $exam->groupExamIds())
            ->whereHas('learner')
            ->get()
            ->sortBy(fn ($result) => [$result->learner->last_name, $result->learner->first_name])
            ->values();

        abort_if($results->isEmpty(), 422, 'No marks have been entered for this exam.');

        return Pdf::loadView('pdf.exam-result-cards', compact('exam', 'results'))
            ->setPaper('a4', 'portrait')
            ->download('results-report-cards-' . $exam->id . '.pdf');
    }

    public function meritList(Exam $exam): Response
    {
        $this->authorizeExamReports($exam);
        $this->ensureGroupPublished($exam);
        $results = ExamResult::with(['learner.schoolClass'])
            ->whereIn('exam_id', $exam->groupExamIds())
            ->whereHas('learner')
            ->get()
            ->groupBy('learner_id')
            ->map(function ($learnerResults) {
                $first = $learnerResults->first();
                $first->marks_obtained = $learnerResults->sum(fn ($result) => (float) ($result->marks_obtained ?? 0));
                $first->total_marks = $learnerResults->sum(fn ($result) => (float) $result->total_marks);
                $first->grade = null;
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
