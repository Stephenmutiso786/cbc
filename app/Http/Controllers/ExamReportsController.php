<?php

namespace App\Http\Controllers;

use App\Enums\RubricLevel;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamReportExport;
use App\Models\TeacherSubjectAllocation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExamReportsController extends Controller
{
    public function resultCards(Exam $exam): Response
    {
        try {
            $this->authorizeExamReports($exam);
            $this->ensureGroupPublished($exam);

            // Use one browser-print path for every class size. This avoids
            // sending identical report requests through two renderers with
            // different memory and timeout behaviour.
            return response()->view('pdf.exam-result-cards', [
                'exam' => $exam,
                'results' => $this->buildResultCardResults($exam),
            ]);
        } catch (Throwable $exception) {
            Log::error('Report-card request failed.', [
                'exam_id' => $exam->id,
                'user_id' => auth()->id(),
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'Report cards could not be generated.',
                'error' => get_class($exception) . ': ' . $exception->getMessage(),
            ], 500);
        }
    }

    public function downloadExport(ExamReportExport $export): Response
    {
        $this->authorizeExamReports($export->exam);
        $this->ensureGroupPublished($export->exam);

        if ($export->status === 'complete' && $export->path) {
            try {
                return response(
                    app(\App\Services\GoogleDriveStorage::class)->contents($export->path),
                    200,
                    [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="results-report-cards-' . $export->exam_id . '.pdf"',
                    ]
                );
            } catch (\Throwable $exception) {
                $export->update(['status' => 'failed', 'error' => 'The generated report could not be read. Please generate it again.']);
                Log::warning('Completed report-card export could not be read.', ['export_id' => $export->id, 'message' => $exception->getMessage()]);
            }
        }

        if ($export->status === 'failed') {
            return response()->view('reports.exam-export-failed', ['export' => $export], 422);
        }

        return response()->view('reports.exam-export-queued', [
            'export' => $export,
            'routeName' => request()->routeIs('teacher.*')
                ? 'teacher.exams.report-cards.export'
                : 'admin.exams.report-cards.export',
        ]);
    }

    public function buildResultCardsPdf(Exam $exam): string
    {
        $results = $this->buildResultCardResults($exam);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.exam-result-cards', compact('exam', 'results'))
            ->setPaper('a4', 'portrait')
            ->output();
    }

    private function buildResultCardResults(Exam $exam)
    {
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
            return $learnerResults->map(function ($result) use ($percentage, $overallGrade, $scale) {
                $result->overall_percentage = round($percentage, 2);
                $result->overall_grade = $overallGrade;
                if ($result->marks_obtained !== null && $result->rubric_level === null) {
                    $result->rubric_level = $this->rubricForPercentage($result->percentage, $scale);
                }
                if ($result->marks_obtained !== null && ! $result->grade) {
                    $result->grade = $scale?->gradeForPercent($result->percentage) ?: $overallGrade;
                }
                return $result;
            });
        })->values();

        return $results;
    }

    private function downloadResultCards(Exam $exam): Response
    {
        return response()->streamDownload(function () use ($exam): void {
            echo $this->buildResultCardsPdf($exam);
        }, 'results-report-cards-' . $exam->id . '.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function meritList(Exam $exam): Response
    {
        $this->authorizeExamReports($exam);
        $this->ensureGroupPublished($exam);
        $exam->load('schoolClass');
        $scale = $exam->schoolClass?->gradingScale()->first();
        $examIds = $exam->groupExamIds();
        $subjects = Exam::with('learningArea')->whereIn('id', $examIds)->orderBy('id')->get();
        $subjectResults = ExamResult::with(['learner.schoolClass'])
            ->whereIn('exam_id', $examIds)
            ->whereHas('learner')
            ->get();
        $subjectMeans = $subjects->map(function ($subject) use ($subjectResults): array {
            $scores = $subjectResults->where('exam_id', $subject->id)
                ->filter(fn ($result) => $result->marks_obtained !== null)
                ->map(fn ($result) => $result->total_marks > 0 ? (float) $result->marks_obtained / (float) $result->total_marks * 100 : 0);
            return ['name' => $subject->learningArea?->name ?? 'Learning area', 'mean' => round($scores->avg() ?: 0, 2), 'entries' => $scores->count()];
        })->values();
        $results = $subjectResults
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
                $first->subject_scores = $learnerResults->mapWithKeys(fn ($result) => [$result->exam_id => [
                    'marks' => $result->marks_obtained, 'total' => $result->total_marks, 'grade' => $result->grade,
                ]])->all();
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

        return Pdf::loadView('pdf.exam-merit-list', [
            'exam' => $exam, 'results' => $ranked, 'subjects' => $subjects,
            'subjectMeans' => $subjectMeans, 'topFive' => $ranked->take(5),
        ])
            ->setPaper('a4', 'landscape')
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

    private function rubricForPercentage(float $percentage, $scale): RubricLevel
    {
        $grade = $scale?->gradeForPercent($percentage);
        $code = strtoupper((string) $grade);

        return match (true) {
            str_starts_with($code, 'EE') || $percentage >= 80 => RubricLevel::ExceedsExpectation,
            str_starts_with($code, 'ME') || $percentage >= 50 => RubricLevel::MeetsExpectation,
            str_starts_with($code, 'AE') || $percentage >= 30 => RubricLevel::ApproachesExpectation,
            default => RubricLevel::BelowExpectation,
        };
    }

    private function authorizeExamReports(Exam $exam): void
    {
        if (auth()->user()->can('manage exams') || auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal'])) {
            return;
        }

        $staff = auth()->user()->staffMember;
        abort_unless($staff, 403);
        $examIds = $exam->groupExamIds();
        $groupExams = Exam::whereIn('id', $examIds)->get(['id', 'class_id', 'learning_area_id', 'term', 'academic_year']);
        $classIds = $staff->classes()->pluck('id');
        $groupClassIds = $groupExams->pluck('class_id')->filter()->unique();
        $isClassTeacher = $groupClassIds->intersect($classIds)->isNotEmpty();
        $areaIds = Exam::whereIn('id', $examIds)->pluck('learning_area_id');
        $isAllocatedTeacher = TeacherSubjectAllocation::where('teacher_id', $staff->id)
            ->whereIn('class_id', $groupClassIds)->whereIn('learning_area_id', $areaIds)
            ->where('academic_year', (string) $exam->academic_year)->where('term', (int) $exam->term)
            ->where('is_active', true)->exists();

        abort_unless($isClassTeacher || $isAllocatedTeacher, 403);
    }

    private function ensureGroupPublished(Exam $exam): void
    {
        $group = Exam::whereIn('id', $exam->groupExamIds())->get();
        abort_unless($group->isNotEmpty() && $group->every(fn (Exam $item) => $item->isFullyPublished()), 422, 'Results are not published. The complete exam must be finalized and published first.');
    }
}
