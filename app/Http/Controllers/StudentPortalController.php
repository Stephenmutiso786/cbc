<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\LearningNote;
use Illuminate\Http\Request;

class StudentPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $learner = $this->learner($request);
        $results = $learner->examResults()->whereHas('exam', fn ($query) => $query
            ->where('exam_state', 'published')->where('status', 'published')->where('marks_status', 'approved'));

        return view('student.dashboard', [
            'learner' => $learner,
            'resultCount' => $results->count(),
            'average' => round((float) $results->get()->avg(fn ($result) => $result->percentage), 1),
            'notesCount' => LearningNote::published()->where('grade_level', $learner->grade_level?->value ?? $learner->grade_level)->count(),
        ]);
    }

    public function results(Request $request)
    {
        $learner = $this->learner($request);
        $results = $learner->examResults()->with(['exam.learningArea'])
            ->whereHas('exam', fn ($query) => $query->where('exam_state', 'published')->where('status', 'published')->where('marks_status', 'approved'))
            ->latest()->get();

        return view('student.results', compact('learner', 'results'));
    }

    public function notes(Request $request)
    {
        $learner = $this->learner($request);
        $notes = LearningNote::published()->with('learningArea')
            ->where('grade_level', $learner->grade_level?->value ?? $learner->grade_level)
            ->where('academic_year', config('school.academic_year'))
            ->latest()->get();

        return view('student.notes', compact('learner', 'notes'));
    }

    private function learner(Request $request): Learner
    {
        $learner = $request->user()->learner()->with('schoolClass')->first();
        abort_unless($learner, 403, 'Your learner account is not linked to a student record. Ask the school administrator to link it.');

        return $learner;
    }
}
