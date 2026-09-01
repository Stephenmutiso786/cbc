<?php

namespace App\Livewire\Admin;

use App\Models\LearningArea;
use App\Models\SchoolClass;
use App\Models\StaffMember;
use App\Models\TeacherSubjectAllocation;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AcademicSetup extends Component
{
    public string $tab = 'classes';
    public bool $showClassForm = false;
    public bool $showAllocationForm = false;
    public bool $showSubjectForm = false;
    public ?int $editingClassId = null;
    public array $classForm = ['name' => '', 'grade_level' => '', 'stream' => '', 'capacity' => 45, 'academic_year' => '', 'class_teacher_id' => ''];
    public array $allocationForm = ['teacher_id' => '', 'class_id' => '', 'learning_area_id' => '', 'term' => 1, 'academic_year' => ''];
    public array $subjectForm = ['class_id' => '', 'learning_area_id' => '', 'lessons_per_week' => 5];
    public ?string $notice = null;

    public function mount(): void
    {
        $this->classForm['academic_year'] = (string) config('school.academic_year');
        $this->allocationForm['academic_year'] = (string) config('school.academic_year');
    }

    public function createClass(): void
    {
        $this->editingClassId = null;
        $this->classForm = ['name' => '', 'grade_level' => '', 'stream' => '', 'capacity' => 45, 'academic_year' => (string) config('school.academic_year'), 'class_teacher_id' => ''];
        $this->showClassForm = true;
    }

    public function editClass(int $id): void
    {
        $class = SchoolClass::findOrFail($id);
        $this->editingClassId = $id;
        $this->classForm = $class->only(['name', 'grade_level', 'stream', 'capacity', 'academic_year', 'class_teacher_id']);
        $this->showClassForm = true;
    }

    public function saveClass(): void
    {
        $data = $this->validate([
            'classForm.name' => ['required', 'string', 'max:255'],
            'classForm.grade_level' => ['required', 'string', 'max:255'],
            'classForm.stream' => ['nullable', 'string', 'max:100'],
            'classForm.capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'classForm.academic_year' => ['required', 'string', 'max:9'],
            'classForm.class_teacher_id' => ['nullable', 'integer', 'exists:staff_members,id'],
        ])['classForm'];
        $class = $this->editingClassId ? SchoolClass::findOrFail($this->editingClassId) : new SchoolClass();
        $class->fill($data)->save();
        $this->showClassForm = false;
        $this->notice = 'Class saved successfully.';
    }

    public function openAllocation(): void
    {
        $this->allocationForm['academic_year'] = (string) config('school.academic_year');
        $this->showAllocationForm = true;
    }

    public function openSubjectAssignment(?int $classId = null): void
    {
        $this->subjectForm = ['class_id' => $classId ?: '', 'learning_area_id' => '', 'lessons_per_week' => 5];
        $this->showSubjectForm = true;
    }

    public function assignSubject(): void
    {
        $data = $this->validate([
            'subjectForm.class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'subjectForm.learning_area_id' => ['required', 'integer', 'exists:learning_areas,id'],
            'subjectForm.lessons_per_week' => ['required', 'integer', 'min:1', 'max:50'],
        ])['subjectForm'];
        SchoolClass::findOrFail($data['class_id'])->learningAreas()->syncWithoutDetaching([
            $data['learning_area_id'] => ['lessons_per_week' => $data['lessons_per_week'], 'is_active' => true],
        ]);
        $this->showSubjectForm = false;
        $this->notice = 'Subject assigned to class successfully.';
    }

    public function removeSubject(int $classId, int $learningAreaId): void
    {
        SchoolClass::findOrFail($classId)->learningAreas()->detach($learningAreaId);
        $this->notice = 'Subject removed from class.';
    }

    public function saveAllocation(): void
    {
        $data = $this->validate([
            'allocationForm.teacher_id' => ['required', 'integer', 'exists:staff_members,id'],
            'allocationForm.class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'allocationForm.learning_area_id' => ['required', 'integer', 'exists:learning_areas,id'],
            'allocationForm.term' => ['required', 'integer', 'between:1,3'],
            'allocationForm.academic_year' => ['required', 'string', 'max:9'],
        ])['allocationForm'];
        TeacherSubjectAllocation::updateOrCreate(
            ['teacher_id' => $data['teacher_id'], 'class_id' => $data['class_id'], 'learning_area_id' => $data['learning_area_id'], 'term' => $data['term'], 'academic_year' => $data['academic_year']],
            ['is_active' => true, 'created_by' => auth()->id()]
        );
        SchoolClass::findOrFail($data['class_id'])->learningAreas()->syncWithoutDetaching([
            $data['learning_area_id'] => ['is_active' => true],
        ]);
        $this->showAllocationForm = false;
        $this->notice = 'Teacher allocation saved successfully.';
    }

    public function removeAllocation(int $id): void
    {
        TeacherSubjectAllocation::findOrFail($id)->delete();
        $this->notice = 'Teacher allocation removed.';
    }

    public function render()
    {
        return view('livewire.admin.academic-setup', [
            'classes' => SchoolClass::forConfiguredGrades()->with(['classTeacher', 'learningAreas'])->orderBy('grade_level')->orderBy('name')->get(),
            'staff' => StaffMember::active()->orderBy('last_name')->get(),
            'learningAreas' => LearningArea::where('is_active', true)->orderBy('name')->get(),
            'allocations' => TeacherSubjectAllocation::with(['teacher', 'schoolClass', 'learningArea'])->latest()->get(),
            'grades' => array_merge(...array_values(config('school.grade_levels'))),
        ])->layout('layouts.admin');
    }
}
