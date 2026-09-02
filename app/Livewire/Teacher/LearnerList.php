<?php

namespace App\Livewire\Teacher;

use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\TeacherSubjectAllocation;
use Livewire\Component;

class LearnerList extends Component
{
    public string $classId = '';
    public string $search = '';

    public function render()
    {
        $user = auth()->user();
        $staff = $user->staffMember;
        $isAdmin = $user->hasAnyRole(['admin', 'super-admin']);
        $bandLevels = $user->gradeBandLevels();
        $allocatedIds = $staff
            ? TeacherSubjectAllocation::where('teacher_id', $staff->id)->where('is_active', true)->pluck('class_id')
            : collect();

        $classes = $isAdmin
            ? SchoolClass::active()->forConfiguredGrades()->orderBy('grade_level')->orderBy('name')->get()
            : SchoolClass::active()->forConfiguredGrades()->where(function ($query) use ($staff, $allocatedIds) {
                $query->whereIn('id', $allocatedIds);
                if ($staff) $query->orWhere('class_teacher_id', $staff->id);
            })->when($bandLevels, fn ($query) => $query->whereIn('grade_level', $bandLevels))
            ->orderBy('grade_level')->orderBy('name')->get();

        $learners = Learner::with('schoolClass')
            ->where('is_active', true)
            ->when($this->classId, fn ($query) => $query->where('class_id', $this->classId))
            ->when($this->search, fn ($query) => $query->where(function ($nested) {
                $nested->where('first_name', 'like', '%' . $this->search . '%')
                    ->orWhere('last_name', 'like', '%' . $this->search . '%')
                    ->orWhere('admission_number', 'like', '%' . $this->search . '%');
            }))
            ->whereIn('class_id', $classes->pluck('id'))
            ->orderBy('last_name')->orderBy('first_name')->get();

        return view('livewire.teacher.learner-list', compact('classes', 'learners'))->layout('layouts.teacher');
    }
}
