<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\TeacherSubjectAllocation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherClassListController extends Controller
{
    public function print(Request $request, SchoolClass $schoolClass): View
    {
        $user = $request->user();
        $staff = $user->staffMember;
        $isAdmin = $user->hasAnyRole(['school-admin', 'super-admin']);
        $allocated = $staff && TeacherSubjectAllocation::query()
            ->where('teacher_id', $staff->id)
            ->where('class_id', $schoolClass->id)
            ->where('is_active', true)
            ->exists();

        abort_unless($isAdmin || ($staff && $schoolClass->class_teacher_id === $staff->id) || $allocated, 403);

        $learners = $schoolClass->learners()
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('teacher.classes.print', compact('schoolClass', 'learners'));
    }
}
