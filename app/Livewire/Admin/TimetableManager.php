<?php

namespace App\Livewire\Admin;

use App\Models\LearningArea;
use App\Models\SchoolClass;
use App\Models\TeacherSubjectAllocation;
use App\Models\TimetableSlot;
use App\Services\TimetableTemplateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\Tenant;
use Livewire\Component;

class TimetableManager extends Component
{
    public string $academicYear = '';
    public int $term = 1;
    public string $classId = '';
    public string $notice = '';
    public array $readiness = [];

    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    public function mount(): void
    {
        $this->academicYear = (string) config('school.academic_year');
        $this->term = (int) config('school.current_term');
    }

    public function generate(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'academicYear' => ['required', 'string', 'max:9'],
            'term' => ['required', 'integer', 'between:1,3'],
            'classId' => ['nullable', 'integer', 'exists:school_classes,id'],
        ]);

        $classes = $this->selectedClasses();
        $missing = $this->missingAllocations($classes);
        if ($missing !== []) {
            $examples = collect($missing)->take(4)->map(fn (array $item) => "{$item['class']} — {$item['subject']}")->join('; ');
            throw ValidationException::withMessages([
                'academicYear' => count($missing) . " subject teacher assignment(s) are missing for Term {$this->term}. Add them in Academic Setup, or use ‘Assign class teachers’ where each class already has a real class teacher. Missing: {$examples}" . (count($missing) > 4 ? '…' : ''),
            ]);
        }

        $slots = $this->buildSchedule($classes);
        if ($slots === []) {
            throw ValidationException::withMessages(['academicYear' => 'No active classes with assigned subjects are available to schedule.']);
        }
        DB::transaction(function () use ($slots): void {
            // Regenerating a single class must not erase every other class's
            // published/draft timetable for the same term.
            TimetableSlot::where('academic_year', $this->academicYear)
                ->where('term', (string) $this->term)
                ->when($this->classId, fn ($query) => $query->where('class_id', (int) $this->classId))
                ->delete();
            TimetableSlot::insert($slots);
        });
        $this->notice = count($slots) . ' lessons generated as a draft. Review conflicts, then publish.';
    }

    /**
     * Creates only missing subject allocations for classes that already have
     * an active, real class teacher. It never replaces a subject teacher the
     * school has explicitly chosen. This gives small schools a usable
     * starting timetable while keeping assignments visible and editable.
     */
    public function assignClassTeachers(): void
    {
        abort_unless($this->canManage(), 403);

        $classes = $this->selectedClasses()->load('classTeacher');
        if ($classes->isEmpty()) {
            $this->addError('academicYear', 'Select an active class before assigning class teachers.');
            return;
        }

        $unready = $classes->filter(fn (SchoolClass $class) => ! $class->classTeacher || ! $class->classTeacher->is_active || $class->classTeacher->staff_type !== 'teaching');
        if ($unready->isNotEmpty()) {
            $this->addError('academicYear', 'Set an active teaching class teacher first for: ' . $unready->pluck('name')->join(', ') . '.');
            return;
        }

        $created = 0;
        DB::transaction(function () use ($classes, &$created): void {
            foreach ($classes as $class) {
                foreach ($class->learningAreas as $area) {
                    $allocation = TeacherSubjectAllocation::firstOrCreate(
                        [
                            'teacher_id' => $class->class_teacher_id,
                            'class_id' => $class->id,
                            'learning_area_id' => $area->id,
                            'term' => $this->term,
                            'academic_year' => $this->academicYear,
                        ],
                        ['is_active' => true, 'created_by' => auth()->id()],
                    );
                    if ($allocation->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        });

        $this->notice = $created
            ? "{$created} missing allocation(s) created from real class-teacher assignments. Review them in Academic Setup, then generate the draft timetable."
            : 'Every selected class subject already has a teacher allocation for this term.';
    }

    public function publish(): void
    {
        abort_unless($this->canManage(), 403);
        $count = TimetableSlot::where('academic_year', $this->academicYear)->where('term', (string) $this->term)
            ->when($this->classId, fn ($query) => $query->where('class_id', (int) $this->classId))
            ->update(['is_active' => true]);
        $this->notice = $count ? "{$count} lessons published to teachers." : 'Generate the timetable before publishing.';
    }

    public function unpublish(): void
    {
        abort_unless($this->canManage(), 403);
        TimetableSlot::where('academic_year', $this->academicYear)->where('term', (string) $this->term)
            ->when($this->classId, fn ($query) => $query->where('class_id', (int) $this->classId))
            ->update(['is_active' => false]);
        $this->notice = 'Timetable unpublished for editing.';
    }

    public function render()
    {
        $classes = $this->selectedClasses();
        $missing = $this->missingAllocations($classes);
        $this->readiness = [
            'classes' => $classes->count(),
            'subjects' => $classes->sum(fn (SchoolClass $class) => $class->learningAreas->count()),
            'missing' => count($missing),
            'class_teachers_missing' => $classes->filter(fn (SchoolClass $class) => ! $class->class_teacher_id)->count(),
        ];
        $selectedClass = $this->classId ? SchoolClass::find((int) $this->classId) : null;
        $slots = TimetableSlot::with(['schoolClass', 'learningArea', 'teacher'])
            ->where('academic_year', $this->academicYear)->where('term', (string) $this->term)
            ->when($this->classId, fn ($query) => $query->where('class_id', (int) $this->classId))
            ->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 ELSE 5 END")
            ->orderBy('start_time')->get();

        return view('livewire.admin.timetable-manager', [
            'slots' => $slots,
            'gridSlots' => $this->classId ? $slots->keyBy(fn (TimetableSlot $slot) => $slot->day_of_week . '|' . substr($slot->start_time, 0, 5)) : collect(),
            'gridPeriods' => $selectedClass ? $this->periodsFor($selectedClass) : [],
            'classes' => SchoolClass::where('is_active', true)->with('learningAreas')->orderBy('grade_level')->orderBy('name')->get(),
            'readiness' => $this->readiness,
        ])->layout('layouts.admin');
    }

    private function selectedClasses(): Collection
    {
        return SchoolClass::where('is_active', true)
            ->when($this->classId, fn ($query) => $query->whereKey((int) $this->classId))
            ->with('learningAreas')->orderBy('grade_level')->orderBy('name')->get();
    }

    /** @return array<int, array{class:string,subject:string}> */
    private function missingAllocations(Collection $classes): array
    {
        if ($classes->isEmpty()) {
            return [];
        }
        $assigned = TeacherSubjectAllocation::where('academic_year', $this->academicYear)
            ->where('term', $this->term)->where('is_active', true)
            ->whereIn('class_id', $classes->pluck('id'))
            ->get()->mapWithKeys(fn ($allocation) => [$allocation->class_id . ':' . $allocation->learning_area_id => true]);
        $missing = [];
        foreach ($classes as $class) {
            foreach ($class->learningAreas as $area) {
                if (! isset($assigned[$class->id . ':' . $area->id])) {
                    $missing[] = ['class' => $class->name, 'subject' => $area->name];
                }
            }
        }
        return $missing;
    }

    private function buildSchedule(Collection $classes): array
    {
        $allocations = TeacherSubjectAllocation::where('academic_year', $this->academicYear)->where('term', $this->term)->where('is_active', true)->get()->groupBy(fn ($allocation) => $allocation->class_id . ':' . $allocation->learning_area_id);
        $occupied = ['class' => [], 'teacher' => [], 'venue' => []];
        // When generating one class, preserve all other classes' slots as
        // occupied. That makes teacher and specialist-room conflict checks
        // real instead of accidentally scheduling over them.
        if ($this->classId) {
            TimetableSlot::where('academic_year', $this->academicYear)
                ->where('term', (string) $this->term)
                ->where('class_id', '!=', (int) $this->classId)
                ->get()
                ->each(function (TimetableSlot $slot) use (&$occupied): void {
                    $this->occupyRange($occupied, 'class', (int) $slot->class_id, $slot->day_of_week, $slot->start_time, $slot->end_time);
                    $this->occupyRange($occupied, 'teacher', (int) $slot->teacher_id, $slot->day_of_week, $slot->start_time, $slot->end_time);
                    if ($slot->venue) {
                        $this->occupyRange($occupied, 'venue', $slot->venue, $slot->day_of_week, $slot->start_time, $slot->end_time);
                    }
                });
        }
        $rows = [];

        foreach ($classes as $class) {
            $periods = $this->periodsFor($class);
            $tasks = [];
            foreach ($class->learningAreas as $area) {
                $allocation = $allocations->get($class->id . ':' . $area->id)?->first();
                if (! $allocation) {
                    throw ValidationException::withMessages(['academicYear' => "{$class->name} has no teacher allocated for {$area->name} in Term {$this->term}."]);
                }
                $weeklyLessons = (int) ($area->pivot?->lessons_per_week ?: $this->weeklyQuota($class->grade_level, $area->name));
                foreach (array_fill(0, max(1, $weeklyLessons), 1) as $_) {
                    $tasks[] = ['area' => $area, 'teacher' => $allocation->teacher_id, 'length' => 1];
                }
            }
            $tasks = $this->makePracticalDoubles($tasks);
            usort($tasks, fn ($a, $b) => ($b['length'] <=> $a['length']) ?: ($this->isPractical($b['area']->name) <=> $this->isPractical($a['area']->name)));
            $subjectDays = [];
            foreach ($tasks as $task) {
                $placed = false;
                $candidates = $this->candidateStarts($task['area']->name, $task['length'], $periods);
                foreach ($candidates as [$dayIndex, $startPeriod]) {
                    $day = self::DAYS[$dayIndex];
                    if (in_array($dayIndex, $subjectDays[$task['area']->id] ?? [], true) && $this->isCore($task['area']->name)) {
                        continue;
                    }
                    $venue = $this->venueFor($task['area']->name);
                    [$startTime, $endTime] = $this->slotRange($periods, $startPeriod, $task['length']);
                    if ($this->conflicts($class->id, $task['teacher'], $venue, $day, $startTime, $endTime, $occupied)) {
                        continue;
                    }
                    for ($offset = 0; $offset < $task['length']; $offset++) {
                        $time = $periods[$startPeriod + $offset];
                        $rows[] = [
                            // insert() bypasses the BelongsToSchool model
                            // event, so the tenant key must be explicit.
                            'school_id' => Tenant::id(),
                            'class_id' => $class->id, 'learning_area_id' => $task['area']->id, 'teacher_id' => $task['teacher'],
                            'day_of_week' => $day, 'start_time' => $time[0] . ':00', 'end_time' => $time[1] . ':00',
                            'venue' => $venue, 'academic_year' => $this->academicYear, 'term' => (string) $this->term,
                            'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
                        ];
                        $this->occupyRange($occupied, 'class', (int) $class->id, $day, $time[0] . ':00', $time[1] . ':00');
                        $this->occupyRange($occupied, 'teacher', (int) $task['teacher'], $day, $time[0] . ':00', $time[1] . ':00');
                        if ($venue) {
                            $this->occupyRange($occupied, 'venue', $venue, $day, $time[0] . ':00', $time[1] . ':00');
                        }
                    }
                    $subjectDays[$task['area']->id][] = $dayIndex;
                    $placed = true;
                    break;
                }
                if (! $placed) {
                    throw ValidationException::withMessages(['academicYear' => "No conflict-free timetable slot is available for {$class->name} - {$task['area']->name}."]);
                }
            }
        }
        return $rows;
    }

    private function makePracticalDoubles(array $tasks): array
    {
        $result = [];
        $paired = [];
        $consumed = [];
        foreach ($tasks as $index => $task) {
            if (($task['length'] ?? 1) === 0) continue;
            if (isset($consumed[$index])) continue;
            $id = $task['area']->id;
            $pairIndex = null;
            if ($this->isPractical($task['area']->name) && ! isset($paired[$id])) {
                foreach (array_slice($tasks, $index + 1, null, true) as $nextIndex => $nextTask) {
                    if ($nextTask['area']->id === $id && ($nextTask['length'] ?? 1) > 0) {
                        $pairIndex = $nextIndex;
                        break;
                    }
                }
            }
            if ($pairIndex !== null) {
                $task['length'] = 2;
                $paired[$id] = true;
                $consumed[$pairIndex] = true;
            }
            $result[] = $task;
        }
        return array_values(array_filter($result, fn ($task) => ($task['length'] ?? 1) > 0));
    }

    /**
     * @param array<int, array{0:string,1:string}> $periods
     * @return array<int, array{0:int,1:int}>
     */
    private function candidateStarts(string $name, int $length, array $periods): array
    {
        $starts = [];
        $periodCount = count($periods);
        foreach (self::DAYS as $dayIndex => $_day) {
            $periodRange = $this->isPractical($name) ? range(0, max(0, min(3, $periodCount - $length))) : range(0, max(0, $periodCount - $length));
            foreach ($periodRange as $period) {
                if ($this->isContiguousRange($periods, $period, $length)) {
                    $starts[] = [$dayIndex, $period];
                }
            }
        }
        return $starts;
    }

    /**
     * @param array<int, array{0:string,1:string}> $periods
     */
    private function isContiguousRange(array $periods, int $startPeriod, int $length): bool
    {
        for ($offset = 0; $offset < $length - 1; $offset++) {
            if (! isset($periods[$startPeriod + $offset + 1])) {
                return false;
            }

            if ($periods[$startPeriod + $offset][1] !== $periods[$startPeriod + $offset + 1][0]) {
                return false;
            }
        }

        return true;
    }

    private function conflicts(int $classId, int $teacherId, ?string $venue, string $day, string $startTime, string $endTime, array $occupied): bool
    {
        if ($this->hasRangeConflict($occupied, 'class', (string) $classId, $day, $startTime, $endTime)) {
            return true;
        }
        if ($this->hasRangeConflict($occupied, 'teacher', (string) $teacherId, $day, $startTime, $endTime)) {
            return true;
        }
        if ($venue && $this->hasRangeConflict($occupied, 'venue', $venue, $day, $startTime, $endTime)) {
            return true;
        }
        return false;
    }

    /**
     * @param array<int, array{0:string,1:string}> $periods
     * @return array{0:string,1:string}
     */
    private function slotRange(array $periods, int $startPeriod, int $length): array
    {
        return [$periods[$startPeriod][0], $periods[$startPeriod + $length - 1][1]];
    }

    /**
     * @return array<int, array{0:string,1:string}>
     */
    private function periodsFor(SchoolClass $class): array
    {
        return app(TimetableTemplateService::class)->periodsForClass($class);
    }

    private function isJss(string $grade): bool { return in_array($grade, ['Grade 7', 'Grade 8', 'Grade 9'], true); }
    private function isCore(string $name): bool { return in_array(strtolower($name), ['english', 'kiswahili', 'mathematics'], true); }
    private function isPractical(string $name): bool { return str_contains(strtolower($name), 'science') || str_contains(strtolower($name), 'agriculture') || str_contains(strtolower($name), 'creative') || str_contains(strtolower($name), 'technical'); }
    private function venueFor(string $name): ?string { $name = strtolower($name); return str_contains($name, 'science') ? 'Science Lab' : (str_contains($name, 'technical') ? 'Computer Lab' : (str_contains($name, 'agriculture') || str_contains($name, 'creative') ? 'Practical Field' : null)); }
    private function weeklyQuota(string $grade, string $name): int
    {
        $name = strtolower($name);
        if ($this->isJss($grade)) return in_array($name, ['english', 'kiswahili', 'mathematics', 'integrated science'], true) ? 5 : 4;
        if (in_array($grade, ['Grade 4', 'Grade 5', 'Grade 6'], true)) return in_array($name, ['english', 'kiswahili', 'mathematics'], true) ? 5 : 4;
        return 5;
    }

    private function occupyRange(array &$occupied, string $bucket, int|string $resourceId, string $day, string $startTime, string $endTime): void
    {
        $occupied[$bucket][(string) $resourceId][$day][] = [
            'start' => $this->timeToMinutes(substr($startTime, 0, 5)),
            'end' => $this->timeToMinutes(substr($endTime, 0, 5)),
        ];
    }

    private function hasRangeConflict(array $occupied, string $bucket, string $resourceId, string $day, string $startTime, string $endTime): bool
    {
        $start = $this->timeToMinutes(substr($startTime, 0, 5));
        $end = $this->timeToMinutes(substr($endTime, 0, 5));

        foreach ($occupied[$bucket][$resourceId][$day] ?? [] as $range) {
            if ($start < $range['end'] && $end > $range['start']) {
                return true;
            }
        }

        return false;
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time, 2) + [0, 0]);

        return $hour * 60 + $minute;
    }

    private function canManage(): bool { return auth()->user()->can('manage timetable'); }
}
