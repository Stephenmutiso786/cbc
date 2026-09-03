<?php

namespace App\Livewire\Admin;

use App\Models\LearningArea;
use App\Models\SchoolClass;
use App\Models\TeacherSubjectAllocation;
use App\Models\TimetableSlot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class TimetableManager extends Component
{
    public string $academicYear = '';
    public int $term = 1;
    public string $classId = '';
    public string $notice = '';

    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    private const TIMES = [
        ['08:00', '08:40'], ['08:40', '09:20'], ['09:20', '10:00'], ['10:20', '11:00'],
        ['11:00', '11:40'], ['12:20', '13:00'], ['13:00', '13:40'], ['13:40', '14:20'],
    ];

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

        $slots = $this->buildSchedule();
        if ($slots === []) {
            throw ValidationException::withMessages(['academicYear' => 'No active classes with assigned subjects are available to schedule.']);
        }
        DB::transaction(function () use ($slots): void {
            TimetableSlot::where('academic_year', $this->academicYear)->where('term', (string) $this->term)->delete();
            TimetableSlot::insert($slots);
        });
        $this->notice = count($slots) . ' lessons generated as a draft. Review conflicts, then publish.';
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
        return view('livewire.admin.timetable-manager', [
            'slots' => TimetableSlot::with(['schoolClass', 'learningArea', 'teacher'])
                ->where('academic_year', $this->academicYear)->where('term', (string) $this->term)
                ->when($this->classId, fn ($query) => $query->where('class_id', (int) $this->classId))
                ->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 ELSE 5 END")
                ->orderBy('start_time')->get(),
            'classes' => SchoolClass::forConfiguredGrades()->where('is_active', true)->with('learningAreas')->orderBy('grade_level')->orderBy('name')->get(),
        ])->layout('layouts.admin');
    }

    private function buildSchedule(): array
    {
        $classes = SchoolClass::forConfiguredGrades()->where('is_active', true)
            ->when($this->classId, fn ($query) => $query->whereKey((int) $this->classId))
            ->with('learningAreas')->orderBy('grade_level')->orderBy('name')->get();
        $allocations = TeacherSubjectAllocation::where('academic_year', $this->academicYear)->where('term', $this->term)->where('is_active', true)->get()->groupBy(fn ($allocation) => $allocation->class_id . ':' . $allocation->learning_area_id);
        $occupied = ['class' => [], 'teacher' => [], 'venue' => []];
        $rows = [];

        foreach ($classes as $class) {
            $periods = count($this->periodsFor($class));
            $tasks = [];
            foreach ($class->learningAreas as $area) {
                $allocation = $allocations->get($class->id . ':' . $area->id)?->first();
                if (! $allocation) {
                    throw ValidationException::withMessages(['academicYear' => "{$class->name} has no teacher allocated for {$area->name} in Term {$this->term}."]);
                }
                foreach (array_fill(0, $this->weeklyQuota($class->grade_level, $area->name), 1) as $_) {
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
                    $key = $class->id . ':' . $day . ':' . $startPeriod;
                    if (in_array($dayIndex, $subjectDays[$task['area']->id] ?? [], true) && $this->isCore($task['area']->name)) {
                        continue;
                    }
                    $venue = $this->venueFor($task['area']->name);
                    if ($this->conflicts($class->id, $task['teacher'], $venue, $day, $startPeriod, $task['length'], $occupied, $periods)) {
                        continue;
                    }
                    for ($offset = 0; $offset < $task['length']; $offset++) {
                        $period = $startPeriod + $offset;
                        $time = $this->periodsFor($class)[$period];
                        $rows[] = [
                            'class_id' => $class->id, 'learning_area_id' => $task['area']->id, 'teacher_id' => $task['teacher'],
                            'day_of_week' => $day, 'start_time' => $time[0] . ':00', 'end_time' => $time[1] . ':00',
                            'venue' => $venue, 'academic_year' => $this->academicYear, 'term' => (string) $this->term,
                            'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
                        ];
                        $occupied['class'][$class->id . ':' . $day . ':' . $period] = true;
                        $occupied['teacher'][$task['teacher'] . ':' . $day . ':' . $period] = true;
                        if ($venue) $occupied['venue'][$venue . ':' . $day . ':' . $period] = true;
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

    private function candidateStarts(string $name, int $length, int $periods): array
    {
        $starts = [];
        foreach (self::DAYS as $dayIndex => $_day) {
            $periodRange = $this->isPractical($name) ? range(0, max(0, min(3, $periods - $length))) : range(0, max(0, $periods - $length));
            foreach ($periodRange as $period) $starts[] = [$dayIndex, $period];
        }
        return $starts;
    }

    private function conflicts(int $classId, int $teacherId, ?string $venue, string $day, int $start, int $length, array $occupied, int $periods): bool
    {
        for ($offset = 0; $offset < $length; $offset++) {
            $period = $start + $offset;
            if (isset($occupied['class'][$classId . ':' . $day . ':' . $period]) || isset($occupied['teacher'][$teacherId . ':' . $day . ':' . $period])) return true;
            if ($venue && isset($occupied['venue'][$venue . ':' . $day . ':' . $period])) return true;
        }
        return false;
    }

    private function periodsFor(SchoolClass $class): array { return array_slice(self::TIMES, 0, $this->isJss($class->grade_level) ? 8 : 7); }
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
    private function canManage(): bool { return auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal', 'deputy-headteacher', 'deputy']); }
}
