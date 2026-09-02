<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Learner;
use Illuminate\Http\Request;

class ReportVerificationController extends Controller
{
    public function show(Request $request, Exam $exam, Learner $learner)
    {
        abort_unless($exam->isGroupMaster(), 404);
        abort_unless($exam->isFullyPublished(), 404);

        $results = ExamResult::query()
            ->with(['exam.learningArea'])
            ->where('learner_id', $learner->id)
            ->whereIn('exam_id', $exam->groupExamIds())
            ->whereNotNull('marks_obtained')
            ->get()
            ->sortBy(fn (ExamResult $result) => $result->exam?->learningArea?->name)
            ->values();

        abort_if($results->isEmpty(), 404);

        return response()->view('reports.verify', [
            'exam' => $exam->load('schoolClass'),
            'learner' => $learner->load('schoolClass'),
            'results' => $results,
            'verifiedAt' => now(),
        ]);
    }
}
