<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Services\SchoolAnalyticsService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private readonly SchoolAnalyticsService $analytics) {}

    public function index(Request $request)
    {
        $year = (string) config('school.academic_year');
        $exams = Exam::with('learningArea')->where('academic_year', $year)->latest('exam_date')->latest()->get();
        $classes = SchoolClass::active()->forYear($year)->forConfiguredGrades()->orderBy('grade_level')->orderBy('name')->get();
        $exam = $exams->firstWhere('id', (int) $request->input('exam_id')) ?: $exams->first();
        $class = $classes->firstWhere('id', (int) $request->input('class_id')) ?: $classes->first();

        $ranking = collect();
        $subjectBreakdown = collect();
        $trend = collect();
        if ($exam && $class) {
            $ranking = $this->analytics->ranking($exam, $class);
            $subjectBreakdown = $this->analytics->subjectBreakdown($class, $year, $exam->term);
            $trend = $this->analytics->classTrend($class, $year);
        }

        return view('admin.reports.index', compact('exams', 'classes', 'exam', 'class', 'ranking', 'subjectBreakdown', 'trend'));
    }

    public function student(Request $request, Learner $learner)
    {
        $year = (string) config('school.academic_year');
        $exam = Exam::with('learningArea')->where('academic_year', $year)->find((int) $request->input('exam_id'));
        $results = $learner->examResults()->with(['exam.learningArea'])->whereHas('exam', fn ($query) => $query->where('academic_year', $year))->get();
        $selectedResults = $exam ? $results->where('exam_id', $exam->id) : $results->sortByDesc(fn ($result) => $result->exam->exam_date)->take(1);

        return view('admin.reports.student', [
            'learner' => $learner->load('schoolClass'),
            'exam' => $exam,
            'results' => $selectedResults,
            'trend' => $this->analytics->learnerTrend($learner, $year),
        ]);
    }

    public function export(Request $request)
    {
        $year = (string) config('school.academic_year');
        $exam = Exam::where('academic_year', $year)->findOrFail((int) $request->input('exam_id'));
        $class = SchoolClass::active()->forYear($year)->forConfiguredGrades()->findOrFail((int) $request->input('class_id'));
        $rows = $this->analytics->ranking($exam, $class);

        return response()->streamDownload(function () use ($rows, $exam, $class) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Exam', $exam->name]);
            fputcsv($handle, ['Class', $class->name]);
            fputcsv($handle, []);
            fputcsv($handle, ['Position', 'Admission number', 'Learner', 'Marks', 'Possible', 'Percentage', 'Grade']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row['position'], $row['learner']?->admission_number, $row['learner']?->full_name, $row['total'], $row['possible'], $row['percentage'], $row['grade']]);
            }
            fclose($handle);
        }, 'school-analytics.csv', ['Content-Type' => 'text/csv']);
    }
}
