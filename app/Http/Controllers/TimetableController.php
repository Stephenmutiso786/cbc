<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\StaffMember;
use App\Models\TimetableSlot;
use App\Services\TimetableTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    public function publish(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage timetable'), 403);

        $data = $request->validate([
            'academicYear' => ['required', 'string', 'max:9'],
            'term' => ['required', 'integer', 'between:1,3'],
            'classId' => ['nullable', 'integer', 'exists:school_classes,id'],
        ]);

        $count = TimetableSlot::where('academic_year', $data['academicYear'])
            ->where('term', (string) $data['term'])
            ->when($data['classId'] ?? null, fn ($query) => $query->where('class_id', (int) $data['classId']))
            ->update(['is_active' => true]);

        return back()->with('success', $count
            ? "{$count} lessons published to teacher portals."
            : 'Generate the timetable before publishing it.');
    }

    public function unpublish(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage timetable'), 403);

        $data = $request->validate([
            'academicYear' => ['required', 'string', 'max:9'],
            'term' => ['required', 'integer', 'between:1,3'],
            'classId' => ['nullable', 'integer', 'exists:school_classes,id'],
        ]);

        $count = TimetableSlot::where('academic_year', $data['academicYear'])
            ->where('term', (string) $data['term'])
            ->when($data['classId'] ?? null, fn ($query) => $query->where('class_id', (int) $data['classId']))
            ->update(['is_active' => false]);

        return back()->with('success', $count
            ? 'Timetable unpublished for editing.'
            : 'No published timetable was found for the selected filters.');
    }

    public function printSchool(Request $request): View
    {
        abort_unless($request->user()?->can('view timetable'), 403);

        $academicYear = (string) $request->input('academicYear', config('school.academic_year'));
        $term = (string) $request->input('term', config('school.current_term'));
        $classId = $request->input('classId');

        $classes = SchoolClass::query()
            ->where('is_active', true)
            ->with(['learningAreas', 'classTeacher'])
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        $slots = TimetableSlot::with(['schoolClass', 'learningArea', 'teacher'])
            ->where('academic_year', $academicYear)
            ->where('term', $term)
            ->when($classId, fn ($query) => $query->where('class_id', (int) $classId))
            ->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 ELSE 5 END")
            ->orderBy('start_time')
            ->get();

        $printClasses = $classId
            ? $classes->where('id', (int) $classId)->values()
            : $classes;

        $templateService = app(TimetableTemplateService::class);
        $classTimetables = $printClasses->map(fn (SchoolClass $schoolClass) => [
            'class' => $schoolClass,
            'template' => $templateService->templateForClass($schoolClass),
            'slots' => $slots->where('class_id', $schoolClass->id)->keyBy(fn (TimetableSlot $slot) => $slot->day_of_week . '|' . substr($slot->start_time, 0, 5)),
        ]);

        return view('admin.timetable.print', [
            'academicYear' => $academicYear,
            'term' => $term,
            'classTimetables' => $classTimetables,
            'days' => self::DAYS,
        ]);
    }

    public function printTeacher(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->can('view timetable'), 403);

        $staff = $user->staffMember;
        abort_unless($staff, 403, 'This account does not have a linked staff record.');

        $slots = TimetableSlot::with(['schoolClass', 'learningArea'])
            ->where('teacher_id', $staff->id)
            ->where('academic_year', (string) config('school.academic_year'))
            ->where('term', (string) config('school.current_term'))
            ->where('is_active', true)
            ->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 ELSE 5 END")
            ->orderBy('start_time')
            ->get();

        $times = $slots->mapWithKeys(fn (TimetableSlot $slot) => [substr($slot->start_time, 0, 5) => substr($slot->end_time, 0, 5)])->sortKeys();
        $slotMap = $slots->keyBy(fn (TimetableSlot $slot) => $slot->day_of_week . '|' . substr($slot->start_time, 0, 5));

        return view('teacher.timetable.print', [
            'staff' => $staff,
            'slots' => $slots,
            'slotMap' => $slotMap,
            'days' => self::DAYS,
            'times' => $times,
        ]);
    }
}