<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Learner;
use App\Models\SchoolClass;
use Illuminate\Support\Collection;

class SchoolAnalyticsService
{
    public function ranking(Exam $exam, SchoolClass $class): Collection
    {
        $results = ExamResult::with('learner')
            ->where('exam_id', $exam->id)
            ->whereHas('learner', fn ($query) => $query->where('class_id', $class->id)->where('is_active', true))
            ->get();

        return $results->groupBy('learner_id')->map(function (Collection $items) {
            $total = $items->sum(fn (ExamResult $result) => (float) $result->marks_obtained);
            $possible = $items->sum(fn (ExamResult $result) => (float) $result->total_marks);
            $percentage = $possible > 0 ? round($total / $possible * 100, 2) : 0;

            return [
                'learner' => $items->first()->learner,
                'subjects' => $items->count(),
                'total' => $total,
                'possible' => $possible,
                'percentage' => $percentage,
                'grade' => $this->grade($percentage),
            ];
        })->sortByDesc('percentage')->values()->map(function (array $row, int $index) {
            $row['position'] = $index + 1;
            return $row;
        });
    }

    public function subjectBreakdown(SchoolClass $class, string $year, $term): Collection
    {
        $results = ExamResult::with(['exam.learningArea'])
            ->whereHas('learner', fn ($query) => $query->where('class_id', $class->id)->where('is_active', true))
            ->whereHas('exam', function ($query) use ($class, $year, $term) {
                $query->where('academic_year', $year)
                    ->where('term', $term)
                    ->where(fn ($nested) => $nested->where('class_id', $class->id)->orWhereNull('class_id'));
            })->get();

        return $results->groupBy(fn (ExamResult $result) => $result->exam->learningArea?->name ?: 'Unknown subject')
            ->map(function (Collection $items, string $subject) {
                $scores = $items->map(fn (ExamResult $result) => $this->percentage($result));
                $mean = round($scores->avg() ?: 0, 2);

                return [
                    'subject' => $subject,
                    'entries' => $scores->count(),
                    'mean' => $mean,
                    'highest' => round($scores->max() ?: 0, 2),
                    'lowest' => round($scores->min() ?: 0, 2),
                    'grade' => $this->grade($mean),
                ];
            })->sortBy('subject')->values();
    }

    public function classTrend(SchoolClass $class, string $year): Collection
    {
        return ExamResult::with('exam')
            ->whereHas('learner', fn ($query) => $query->where('class_id', $class->id)->where('is_active', true))
            ->whereHas('exam', fn ($query) => $query->where('academic_year', $year))
            ->get()->groupBy('exam_id')->map(function (Collection $items) {
                $possible = $items->sum(fn (ExamResult $result) => (float) $result->total_marks);
                return [
                    'exam' => $items->first()->exam->name,
                    'date' => $items->first()->exam->exam_date,
                    'mean' => round($items->sum(fn (ExamResult $result) => (float) $result->marks_obtained) / max($possible, 1) * 100, 2),
                ];
            })->sortBy('date')->values();
    }

    public function learnerTrend(Learner $learner, string $year): Collection
    {
        return $learner->examResults()->with(['exam.learningArea'])
            ->whereHas('exam', fn ($query) => $query->where('academic_year', $year))
            ->get()->groupBy('exam_id')->map(function (Collection $items) {
                $possible = $items->sum(fn (ExamResult $result) => (float) $result->total_marks);
                $percentage = round($items->sum(fn (ExamResult $result) => (float) $result->marks_obtained) / max($possible, 1) * 100, 2);
                return ['exam' => $items->first()->exam->name, 'subject' => $items->first()->exam->learningArea?->name, 'date' => $items->first()->exam->exam_date, 'percentage' => $percentage, 'grade' => $this->grade($percentage)];
            })->sortBy('date')->values();
    }

    public function percentage(ExamResult $result): float
    {
        return (float) $result->total_marks > 0 ? (float) $result->marks_obtained / (float) $result->total_marks * 100 : 0;
    }

    public function grade(float $percentage): string
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
