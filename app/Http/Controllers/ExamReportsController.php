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
    public function resultCards(string $exam): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $exam = Exam::query()->findOrFail($exam);
            $this->authorizeExamReports($exam);
            $this->ensureGroupPublished($exam);

            $html = view('reports.exam-report-cards-print', $this->buildPrintableReport($exam))->render();

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (Throwable $exception) {
            Log::error('Report-card request failed.', [
                'exam_id' => $exam instanceof Exam ? $exam->id : $exam,
                'user_id' => auth()->id(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    private function buildPrintableReport(Exam $exam): array
    {
        $exam->load('schoolClass');
        $scale = $exam->schoolClass?->gradingScale()->first();
        $merit = $this->buildPrintableMeritList($exam);
        $subjects = collect($merit['subjects']);
        $totalPossible = $subjects->sum(fn (array $subject) => (float) $subject['total']);

        $cards = collect($merit['rows'])->map(function (array $row) use ($exam, $scale, $subjects, $totalPossible): array {
            $totalObtained = 0.0;
            $subjectRows = $subjects->map(function (array $subject) use ($row, $scale, &$totalObtained): array {
                $score = $row['subject_scores'][$subject['id']] ?? null;
                $marks = $score['marks'] ?? null;
                $total = (float) ($score['total'] ?? $subject['total']);

                if ($marks === null) {
                    return [
                        'name' => $subject['name'],
                        'rubric' => '-',
                        'points' => '-',
                        'grade' => '-',
                        'remarks' => 'Did not sit',
                    ];
                }

                $marks = (float) $marks;
                $totalObtained += $marks;
                $percentage = $total > 0 ? ($marks / $total) * 100 : 0;
                $rubric = $scale?->gradeForPercent($percentage)
                    ?: $this->rubricForPercentage($percentage, $scale)->value;

                return [
                    'name' => $subject['name'],
                    'rubric' => $rubric,
                    'points' => $this->rubricPoints($rubric),
                    'grade' => $score['grade'] ?: $rubric,
                    'remarks' => $score['remarks'] ?: 'Keep working consistently.',
                ];
            })->values()->all();

            $overallPercentage = $totalPossible > 0 ? ($totalObtained / $totalPossible) * 100 : 0;

            return [
                'learner' => [
                    'name' => $row['learner']['name'],
                    'admission_number' => $row['learner']['admission_number'],
                    'class' => $exam->schoolClass?->name ?? (string) $exam->grade_level,
                ],
                'subjects' => $subjectRows,
                'subject_count' => $subjects->count(),
                'total_obtained' => $totalObtained,
                'total_possible' => $totalPossible,
                'overall_percentage' => round($overallPercentage, 2),
                'overall_grade' => $this->gradeForPercentage($overallPercentage, $scale),
            ];
        })->sortBy(fn (array $card) => $card['learner']['name'])->values()->all();

        return compact('exam', 'cards');
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
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.exam-merit-list', $this->buildPrintableMeritList($exam))
            ->setPaper('a4', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="merit-list-' . $exam->id . '.pdf"',
        ]);
    }

    private function buildPrintableMeritList(Exam $exam): array
    {
        $exam->load('schoolClass');
        $scale = $exam->schoolClass?->gradingScale()->first();
        $examIds = $exam->groupExamIds();
        $subjects = Exam::with('learningArea')->whereIn('id', $examIds)->orderBy('id')->get();
        $subjectResults = ExamResult::with(['learner.schoolClass', 'exam.learningArea'])
            ->whereIn('exam_id', $examIds)
            ->whereHas('learner')
            ->get();

        abort_if($subjectResults->isEmpty(), 422, 'No marks have been entered for this exam.');

        $subjectMeans = $subjects->map(function ($subject) use ($subjectResults): array {
            $scores = $subjectResults->where('exam_id', $subject->id)
                ->filter(fn ($result) => $result->marks_obtained !== null)
                ->map(fn ($result) => (float) $result->total_marks > 0
                    ? ((float) $result->marks_obtained / (float) $result->total_marks) * 100
                    : 0);

            return [
                'name' => $subject->learningArea?->name ?? 'Learning area',
                'mean' => round($scores->avg() ?: 0, 2),
                'entries' => $scores->count(),
            ];
        })->values()->all();

        $rows = $subjectResults->groupBy('learner_id')->map(function ($learnerResults) use ($scale): array {
            $first = $learnerResults->first();
            $totalPossible = $learnerResults->sum(fn ($result) => (float) $result->total_marks);
            $totalObtained = $learnerResults->sum(fn ($result) => (float) ($result->marks_obtained ?? 0));
            $percentage = $totalPossible > 0 ? ($totalObtained / $totalPossible) * 100 : 0;

            return [
                'learner' => [
                    'name' => $first->learner->full_name,
                    'admission_number' => $first->learner->admission_number,
                ],
                'subject_scores' => $learnerResults->mapWithKeys(function ($result): array {
                    return [$result->exam_id => [
                        'marks' => $result->marks_obtained,
                        'total' => $result->total_marks,
                        'grade' => $result->grade ?: '-',
                        'remarks' => $result->remarks,
                    ]];
                })->all(),
                'total_obtained' => $totalObtained,
                'total_possible' => $totalPossible,
                'percentage' => round($percentage, 1),
                'grade' => $this->gradeForPercentage($percentage, $scale),
            ];
        })->sortByDesc(fn (array $row) => $row['total_possible'] > 0
            ? $row['total_obtained'] / $row['total_possible']
            : 0)->values();

        $position = 0;
        $previousPercentage = null;
        $rows = $rows->map(function (array $row, int $index) use (&$position, &$previousPercentage): array {
            if ($previousPercentage === null || $row['percentage'] < $previousPercentage) {
                $position = $index + 1;
            }
            $previousPercentage = $row['percentage'];
            $row['position'] = $position;
            return $row;
        })->values()->all();

        return [
            'exam' => $exam,
            'subjects' => $subjects->map(fn ($subject): array => [
                'id' => $subject->id,
                'name' => $subject->learningArea?->name ?? 'Subject',
                'total' => $subject->total_marks,
            ])->values()->all(),
            'subjectMeans' => $subjectMeans,
            'rows' => $rows,
            'topFive' => array_slice($rows, 0, 5),
        ];
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

    private function rubricCodeForResult(ExamResult $result, float $percentage, $scale): string
    {
        $scaleCode = $scale?->gradeForPercent($percentage);
        if ($scaleCode) {
            return $scaleCode;
        }

        $raw = strtoupper(trim((string) $result->getRawOriginal('rubric_level')));
        if (preg_match('/^(EE|ME|AE|BE)([12])?$/', $raw)) {
            return $raw;
        }

        return $this->rubricForPercentage($percentage, $scale)->value;
    }

    private function rubricPoints(string $rubric): int|string
    {
        return [
            'EE1' => 8,
            'EE2' => 7,
            'ME1' => 6,
            'ME2' => 5,
            'AE1' => 4,
            'AE2' => 3,
            'BE1' => 2,
            'BE2' => 1,
        ][$rubric] ?? RubricLevel::tryFrom($rubric)?->numericValue() ?? '-';
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
