<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\StaffMember;
use App\Models\TeacherSubjectAllocation;
use App\Models\User;

class ExamPolicy
{
    public function create(User $user): bool { return $user->can('manage exams'); }

    public function markForClass(User $user, Exam $exam, int $classId): bool
    {
        if ($exam->isLocked() || !$user->can('enter marks')) return false;
        if ($user->hasAnyRole(['admin', 'super-admin'])) return true;
        $teacher = StaffMember::where('user_id', $user->id)->first();
        return $teacher && TeacherSubjectAllocation::where([
            'teacher_id' => $teacher->id, 'class_id' => $classId,
            'learning_area_id' => $exam->learning_area_id, 'term' => (int) $exam->term,
            'academic_year' => (string) $exam->academic_year, 'is_active' => true,
        ])->exists();
    }

    public function lockResults(User $user, Exam $exam): bool
    {
        return !$exam->isLocked() && $user->can('publish results');
    }
}
