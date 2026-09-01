<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Learner;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $year = (string) config('school.academic_year');
        $exams = Exam::with('learningArea')->where('academic_year', $year)->latest('exam_date')->latest()->get();
        $classes = SchoolClass::active()->forYear($year)->orderBy('grade_level')->orderBy('name')->get();
        $exam = $exams->firstWhere('id', (int) $request->input('exam_id')) ?: $exams->first();
        $class = $classes->firstWhere('id', (int) $request->input('class_id')) ?: $classes->first();

        $ranking = collect();
        $subjectBreakdown = collect();
        $trend = collect();
        if ($exam && $class) {
            $learnerIds = Learner::where('class_id', $class->id)->where('is_active', true)->pluck('id');
            $results = ExamResult::with(['learner', 'exam.learningArea'])
                ->where('exam_id', $exam->id)->whereIn('learner_id', $learnerIds)->get();
            $ranking = $results->groupBy('learner_id')->map(function ($items) {
                $total = $items->sum(fn ($result) => (float) $result->marks_obtained);
                $possible = $items->sum(fn ($result) => (float) $result->total_marks);
                $percentage = $possible > 0 ? round($total / $possible * 100, 2) : 0;
                return ['learner' => $items->first()->learner, 'subjects' => $items->count(), 'total' => $total, 'percentage' => $percentage, 'grade' => $this->grade($percentage)];
            })->sortByDesc('percentage')->values()->map(function ($row, $index) {
                $row['position'] = $index + 1;
                return $row;
            });
            $subjectBreakdown = $results->groupBy(fn ($result) => $result->exam->learningArea?->name ?: 'Unknown subject')->map(function ($items, $subject) {
                $scores = $items->map(fn ($result) => (float) $result->marks_obtained / max((float) $result->total_marks, 1) * 100);
                return ['subject' => $subject, 'entries' => $scores->count(), 'mean' => round($scores->avg(), 2), 'highest' => round($scores->max() ?: 0, 2), 'lowest' => round($scores->min() ?: 0, 2), 'grade' => $this->grade($scores->avg() ?: 0)];
            })->values();
            $trend = ExamResult::with('exam')->whereIn('learner_id', $learnerIds)->whereHas('exam', fn ($query) => $query->where('academic_year', $year))->get()->groupBy('exam_id')->map(function ($items) {
                $possible = $items->sum(fn ($result) => (float) $result->total_marks);
                return ['exam' => $items->first()->exam->name, 'date' => $items->first()->exam->exam_date, 'mean' => round($items->sum(fn ($result) => (float) $result->marks_obtained) / max($possible, 1) * 100, 2)];
            })->sortBy('date')->values();
        }

        return view('admin.reports.index', compact('exams', 'classes', 'exam', 'class', 'ranking', 'subjectBreakdown', 'trend'));
    }

    private function grade(float $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'A',
            $percentage >= 70 => 'B',
            $percentage >= 60 => 'C',
            $percentage >= 50 => 'D',
            default => 'E',
        };
    }
}
