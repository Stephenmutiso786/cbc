<?php

namespace App\Livewire\Assessment;

use App\Enums\RubricLevel;
use App\Enums\TermEnum;
use App\Models\Assessment;
use App\Models\LearningArea;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Strand;
use App\Models\TeacherSubjectAllocation;
use Livewire\Component;

class BulkAssessmentEntry extends Component
{
    public ?int    $classId         = null;
    public ?int    $learningAreaId  = null;
    public ?int    $strandId        = null;
    public string  $term            = '';
    public string  $academicYear    = '';
    public string  $assessmentType  = 'formative';
    public array   $assessmentData  = []; // keyed by learner_id

    public function mount(): void
    {
        $this->academicYear = config('school.academic_year');
        $this->term         = config('school.current_term');
    }

    public function loadLearners(): void
    {
        if (!$this->classId) return;
        if (!$this->canUseSelection()) {
            $this->addError('classId', 'You are not allocated to this class and learning area.');
            $this->assessmentData = [];
            return;
        }

        $learners = Learner::where('class_id', $this->classId)->where('is_active', true)
            ->orderBy('last_name')->get();

        $this->assessmentData = $learners->mapWithKeys(fn($l) => [
            $l->id => ['rubric_level' => '', 'remarks' => '', 'name' => $l->full_name]
        ])->toArray();
    }

    public function updatedLearningAreaId(): void
    {
        if ($this->classId) $this->loadLearners();
    }

    public function saveAssessments(): void
    {
        $this->validate([
            'classId'        => 'required|exists:school_classes,id',
            'learningAreaId' => 'required|exists:learning_areas,id',
            'term'           => 'required',
            'academicYear'   => 'required',
        ]);
        abort_unless($this->canUseSelection(), 403, 'You are not allocated to this class and learning area.');

        $teacherId = auth()->user()->staffMember?->id;
        $saved = 0;

        foreach ($this->assessmentData as $learnerId => $data) {
            if (empty($data['rubric_level'])) continue;

            Assessment::updateOrCreate(
                [
                    'learner_id'      => $learnerId,
                    'learning_area_id'=> $this->learningAreaId,
                    'strand_id'       => $this->strandId,
                    'term'            => $this->term,
                    'academic_year'   => $this->academicYear,
                    'assessment_type' => $this->assessmentType,
                ],
                [
                    'rubric_level' => $data['rubric_level'],
                    'remarks'      => $data['remarks'] ?? null,
                    'teacher_id'   => $teacherId,
                    'class_id'     => $this->classId,
                    'assessed_date'=> now(),
                ]
            );
            $saved++;
        }

        $this->dispatch('notify', type: 'success', message: "{$saved} assessments saved successfully.");
    }

    public function render()
    {
        $fullAdmin = auth()->user()->hasAnyRole(['admin', 'super-admin']);
        $staffId = auth()->user()->staffMember?->id;
        $allocations = TeacherSubjectAllocation::where('teacher_id', $staffId)->where('is_active', true)
            ->where('academic_year', (string) $this->academicYear);
        return view('livewire.assessment.bulk-assessment-entry', [
            'classes'       => $fullAdmin ? SchoolClass::forConfiguredGrades()->orderBy('grade_level')->get() : SchoolClass::forConfiguredGrades()->whereIn('id', (clone $allocations)->pluck('class_id'))->orderBy('grade_level')->get(),
            'learningAreas' => $fullAdmin ? LearningArea::where('is_active', true)->orderBy('name')->get() : LearningArea::whereIn('id', (clone $allocations)->pluck('learning_area_id'))->where('is_active', true)->orderBy('name')->get(),
            'strands'       => $this->learningAreaId
                ? Strand::where('learning_area_id', $this->learningAreaId)->orderBy('order')->get()
                : collect(),
            'rubricLevels'  => RubricLevel::cases(),
            'terms'         => TermEnum::cases(),
        ])->layout('layouts.teacher');
    }

    private function canUseSelection(): bool
    {
        if (auth()->user()->hasAnyRole(['admin', 'super-admin'])) return true;
        return TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
            ->where('class_id', $this->classId)
            ->where('learning_area_id', $this->learningAreaId)
            ->where('term', (int) $this->term)
            ->where('academic_year', (string) $this->academicYear)
            ->where('is_active', true)->exists();
    }
}
