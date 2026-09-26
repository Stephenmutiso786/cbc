<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\StaffMember;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Services\TimetableValidationService;
use Illuminate\Support\Facades\DB;
use App\Services\TimetableTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class TimetableController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    public function publish(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage timetable'), 403);

        $data = $request->validate(['version_id' => ['required', 'integer', 'exists:timetable_versions,id']]);
        $version = TimetableVersion::findOrFail($data['version_id']);
        if ($version->state !== 'verified') return back()->withErrors(['version_id' => 'Only a verified timetable version can be published.']);
        $report = app(TimetableValidationService::class)->validate($version);
        if (! $report['valid']) { $version->update(['conflict_report' => $report['errors']]); return back()->withErrors(['version_id' => 'The selected timetable no longer passes validation.']); }
        DB::transaction(function () use ($version): void {
            TimetableVersion::where('academic_year', $version->academic_year)->where('term', $version->term)->where('state', 'published')->update(['state' => 'archived', 'archived_by' => auth()->id(), 'archived_at' => now()]);
            TimetableSlot::where('academic_year', $version->academic_year)->where('term', $version->term)->where('is_active', true)->update(['is_active' => false]);
            $version->slots()->update(['is_active' => true]);
            $version->update(['state' => 'published', 'published_by' => auth()->id(), 'published_at' => now()]);
        });
        return back()->with('success', "Timetable version {$version->version_number} published.");
    }

    public function unpublish(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage timetable'), 403);

        $version = TimetableVersion::where('state', 'published')->latest('published_at')->first();
        if (! $version) return back()->with('success', 'No published timetable was found.');
        DB::transaction(function () use ($version): void { $version->slots()->update(['is_active' => false]); $version->update(['state' => 'verified']); });
        return back()->with('success', 'Timetable unpublished and returned to verified status.');
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
            ->where('is_active', true)
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
            'blocks' => $templateService->blocksForClass($schoolClass),
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

        // A historical teacher login may have been created independently of
        // its staff profile. Resolve that same-school link before looking up
        // generated slots, otherwise a valid timetable looks empty.
        $staff = $user->resolvedStaffMember();
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

    public function pdf(TimetableVersion $version, Request $request)
    {
        abort_unless($request->user()?->can('view timetable'), 403);
        $this->ensureReadableVersion($version, $request);
        return Pdf::loadView('pdf.timetable-version', $this->pdfData($version, $request->integer('class_id') ?: null))->setPaper('a4', 'landscape')->download("timetable-v{$version->version_number}.pdf");
    }

    public function teacherPdf(TimetableVersion $version, StaffMember $teacher, Request $request)
    {
        $user = $request->user();
        abort_unless($user?->can('view timetable'), 403);
        $staff = $user->resolvedStaffMember();
        abort_unless($user->can('manage timetable') || ($staff && $staff->id === $teacher->id), 403);
        $this->ensureReadableVersion($version, $request);
        return Pdf::loadView('pdf.timetable-version', $this->pdfData($version, null, $teacher->id))->setPaper('a4', 'landscape')->download("teacher-timetable-{$teacher->id}-v{$version->version_number}.pdf");
    }

    public function allClassesPdf(TimetableVersion $version, Request $request)
    {
        abort_unless($request->user()?->can('manage timetable'), 403);
        $this->ensureReadableVersion($version, $request);
        return Pdf::loadView('pdf.timetable-version', $this->pdfData($version))->setPaper('a4', 'landscape')->download("all-class-timetables-v{$version->version_number}.pdf");
    }

    private function ensureReadableVersion(TimetableVersion $version, Request $request): void
    {
        abort_unless($version->state === 'published' || $request->user()?->can('manage timetable'), 403);
    }

    private function pdfData(TimetableVersion $version, ?int $classId = null, ?int $teacherId = null): array
    {
        $slots = $version->slots()->with(['schoolClass', 'learningArea', 'teacher'])->when($classId, fn ($q) => $q->where('class_id', $classId))->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))->orderBy('day_of_week')->orderBy('start_time')->get();
        return ['version' => $version, 'slots' => $slots, 'school' => $version->school, 'generatedAt' => $version->generated_at ?: $version->created_at, 'teacher' => $teacherId ? StaffMember::find($teacherId) : null];
    }
}
