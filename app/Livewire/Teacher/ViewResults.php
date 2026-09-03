<?php

namespace App\Livewire\Teacher;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Learner;
use App\Models\TeacherSubjectAllocation;
use Illuminate\Support\Collection;
use Livewire\Component;

class ViewResults extends Component
{
    public string $term = '';
    public bool $dashboardOnly = false;

    public function mount(bool $dashboardOnly = false): void
    {
        $this->dashboardOnly = $dashboardOnly;
        $this->term = (string) config('school.current_term');
    }

    public function render()
    {
        $staff = auth()->user()->staffMember;
        $year = (string) config('school.academic_year');
        $classTeacherClassIds = $staff?->classes()->where('academic_year', $year)->pluck('id') ?? collect();
        $allocations = $staff
            ? TeacherSubjectAllocation::with(['schoolClass', 'learningArea'])
                ->where('teacher_id', $staff->id)->where('academic_year', $year)
                ->where('term', (int) $this->term)->where('is_active', true)->get()
            : collect();
        $allocationKeys = $allocations->mapWithKeys(fn ($allocation) => [$allocation->class_id . ':' . $allocation->learning_area_id => true]);
        $classTeacher = $classTeacherClassIds->isNotEmpty();

        $visibleRows = Exam::with(['learningArea', 'schoolClass'])
            ->where('academic_year', $year)->where('term', (int) $this->term)
            ->where('exam_state', 'published')->where('status', 'published')->where('marks_status', 'approved')->get()
            ->filter(fn (Exam $exam) => $classTeacherClassIds->contains((int) $exam->class_id)
                || (bool) ($allocationKeys[$exam->class_id . ':' . $exam->learning_area_id] ?? false));

        $groups = $visibleRows->groupBy(fn (Exam $exam) => $exam->exam_group_id ?: $exam->id)
            ->map(function (Collection $subjects, $rootId) use ($classTeacherClassIds): array {
                $root = $subjects->firstWhere('id', (int) $rootId) ?: $subjects->first();
                $classIds = $subjects->pluck('class_id')->filter()->unique()->values();
                $results = ExamResult::with('learner')
                    ->whereIn('exam_id', $subjects->pluck('id'))
                    ->whereHas('learner', fn ($query) => $query
                        ->where('is_active', true)
                        ->whereIn('class_id', $classIds))
                    ->get();
                $stats = $subjects->map(function (Exam $subject) use ($results): array {
                    $scores = $results->where('exam_id', $subject->id)->filter(fn (ExamResult $result) => $result->marks_obtained !== null)
                        ->map(fn (ExamResult $result) => $result->total_marks > 0 ? (float) $result->marks_obtained / (float) $result->total_marks * 100 : 0);
                    return ['name' => $subject->learningArea?->name ?? 'Learning area', 'mean' => round($scores->avg() ?: 0, 2), 'entries' => $scores->count()];
                })->values();
                $classId = (int) ($root->class_id ?: $subjects->first()->class_id);
                $learners = Learner::whereIn('class_id', $classIds)->where('is_active', true)
                    ->orderBy('last_name')->orderBy('first_name')->get()
                    ->map(function (Learner $learner) use ($subjects, $results): array {
                        $subjectResults = $subjects->map(function (Exam $subject) use ($learner, $results): array {
                            $result = $results->first(fn (ExamResult $item) => (int) $item->exam_id === (int) $subject->id && (int) $item->learner_id === (int) $learner->id);
                            return [
                                'subject' => $subject->learningArea?->name ?? 'Learning area',
                                'marks' => $result?->marks_obtained !== null ? (float) $result->marks_obtained : null,
                                'percentage' => $result?->percentage,
                                'rubric' => $result?->rubric_level?->value ?? (string) ($result?->rubric_level ?? ''),
                                'grade' => $result?->grade,
                            ];
                        })->values();
                        return ['id' => $learner->id, 'name' => $learner->full_name, 'admission_number' => $learner->admission_number, 'subjects' => $subjectResults];
                    })->values();
                return ['id' => (int) $root->id, 'name' => $root->name, 'type' => $root->typeLabel(), 'class' => $root->schoolClass?->name ?: $root->grade_level, 'date' => $root->exam_date?->format('d M Y'), 'subjects' => $stats, 'learners' => $learners, 'mean' => round($stats->pluck('mean')->avg() ?: 0, 2), 'can_download' => $classTeacherClassIds->contains($classId)];
            })->sortByDesc('date')->values();

        $view = view('livewire.teacher.view-results', compact('groups', 'allocations', 'classTeacher'))->with('terms', [1, 2, 3]);

        return $this->dashboardOnly ? $view : $view->layout('layouts.teacher');
    }
}
