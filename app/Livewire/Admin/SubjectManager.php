<?php

namespace App\Livewire\Admin;

use App\Models\LearningArea;
use App\Models\SchoolClass;
use Livewire\Component;

class SubjectManager extends Component
{
    public bool $showForm = false;
    public bool $showAssignmentForm = false;
    public ?int $editingId = null;
    public array $form = ['name' => '', 'code' => '', 'grade_level' => '', 'weekly_lessons' => 5];
    public array $assignmentForm = ['class_id' => '', 'learning_area_id' => '', 'lessons_per_week' => 5];

    public function create(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'code' => '', 'grade_level' => '', 'weekly_lessons' => 5];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->editingId = $id;
        $this->form = LearningArea::findOrFail($id)->only(['name', 'code', 'grade_level', 'weekly_lessons']);
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:50', 'unique:learning_areas,code,' . ($this->editingId ?: 'NULL')],
            'form.grade_level' => ['required', 'string', 'max:255'],
            'form.weekly_lessons' => ['required', 'integer', 'min:1', 'max:50'],
        ])['form'];
        $area = $this->editingId ? LearningArea::findOrFail($this->editingId) : new LearningArea();
        $area->fill($data)->save();
        $this->showForm = false;
        session()->flash('success', 'Learning area saved successfully.');
    }

    public function toggle(int $id): void
    {
        $area = LearningArea::findOrFail($id);
        $area->update(['is_active' => !$area->is_active]);
    }

    public function openAssignment(?int $classId = null): void
    {
        $this->assignmentForm = ['class_id' => $classId ?: '', 'learning_area_id' => '', 'lessons_per_week' => 5];
        $this->showAssignmentForm = true;
    }

    public function assignToClass(): void
    {
        $data = $this->validate([
            'assignmentForm.class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'assignmentForm.learning_area_id' => ['required', 'integer', 'exists:learning_areas,id'],
            'assignmentForm.lessons_per_week' => ['required', 'integer', 'min:1', 'max:50'],
        ])['assignmentForm'];

        SchoolClass::findOrFail($data['class_id'])->learningAreas()->syncWithoutDetaching([
            $data['learning_area_id'] => ['lessons_per_week' => $data['lessons_per_week'], 'is_active' => true],
        ]);
        $this->showAssignmentForm = false;
        session()->flash('success', 'Subject assigned to class successfully.');
    }

    public function removeFromClass(int $classId, int $learningAreaId): void
    {
        SchoolClass::findOrFail($classId)->learningAreas()->detach($learningAreaId);
        session()->flash('success', 'Subject removed from class.');
    }

    public function render()
    {
        return view('livewire.admin.subject-manager', [
            'subjects' => LearningArea::orderBy('name')->get(),
            'classes' => SchoolClass::forConfiguredGrades()->with('learningAreas')->orderBy('grade_level')->orderBy('name')->get(),
            'grades' => array_merge(...array_values(config('school.grade_levels'))),
        ])
            ->layout('layouts.admin');
    }
}
