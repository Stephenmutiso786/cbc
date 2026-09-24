<?php

namespace App\Http\Controllers;

use App\Models\ExamTimetable;
use App\Models\LearningNote;
use App\Models\Newsletter;
use Illuminate\Http\Request;

class PortalContentController extends Controller
{
    public function newsletters()
    {
        return view('portal.newsletters', ['newsletters' => Newsletter::published()->latest('issued_on')->get()]);
    }

    public function studentExamTimetable(Request $request)
    {
        $learner = $request->user()->learner()->with('schoolClass')->firstOrFail();
        return view('portal.exam-timetable', ['title' => 'My Exam Timetable', 'slots' => $this->examSlots([$learner->class_id])]);
    }

    public function parentExamTimetable(Request $request)
    {
        $classIds = $request->user()->guardian?->learners()->pluck('class_id')->filter()->unique()->all() ?? [];
        return view('portal.exam-timetable', ['title' => 'Learners Exam Timetable', 'slots' => $this->examSlots($classIds)]);
    }

    public function teacherExamTimetable(Request $request)
    {
        $staff = $request->user()->resolvedStaffMember();
        abort_unless($staff, 403, 'This account is not linked to a staff record.');
        return view('portal.exam-timetable', ['title' => 'My Invigilation Timetable', 'slots' => ExamTimetable::with(['exam.learningArea', 'schoolClass'])->where('invigilator_id', $staff->id)->where('is_published', true)->orderBy('date')->orderBy('start_time')->get()]);
    }

    public function parentNotes(Request $request)
    {
        $grades = $request->user()->guardian?->learners()->get()->map(fn ($learner) => $learner->grade_level instanceof \BackedEnum ? $learner->grade_level->value : $learner->grade_level)->filter()->unique()->all() ?? [];
        return view('parent.notes.index', ['notes' => LearningNote::published()->with('learningArea')->whereIn('grade_level', $grades)->latest()->get()]);
    }

    private function examSlots(array $classIds)
    {
        return ExamTimetable::with(['exam.learningArea', 'schoolClass'])
            ->whereIn('class_id', $classIds)->where('is_published', true)
            ->orderBy('date')->orderBy('start_time')->get();
    }
}
