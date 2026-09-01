<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ExamReportsController extends Controller
{
    public function resultCards(Exam $exam): Response
    {
        $this->authorizeExamReports();
        $results = ExamResult::with(['learner.schoolClass'])
            ->where('exam_id', $exam->id)
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
        $this->authorizeExamReports();
        $results = ExamResult::with(['learner.schoolClass'])
            ->where('exam_id', $exam->id)
            ->whereHas('learner')
            ->get()
            ->sortByDesc(fn ($result) => $result->total_marks > 0
                ? (float) $result->marks_obtained / (float) $result->total_marks
                : 0)
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

    private function authorizeExamReports(): void
    {
        abort_unless(auth()->user()->can('manage exams') || auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher']), 403);
    }
}
