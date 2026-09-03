<?php

namespace App\Livewire\Admin;

use App\Models\Exam;
use App\Models\ExamTimetable;
use App\Models\StaffMember;
use App\Models\TeacherSubjectAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ExamTimetableManager extends Component
{
    public string $academicYear = '';
    public int $term = 1;
    public string $examGroupId = '';
    public string $classId = '';
    public string $startDate = '';
    public string $notice = '';

    private const TIMES = [
        ['08:00', '10:00'],
        ['10:30', '12:30'],
        ['13:30', '15:30'],
    ];

    public function mount(): void
    {
        $this->academicYear = (string) config('school.academic_year');
        $this->term = (int) config('school.current_term');
        $this->startDate = now()->startOfWeek()->format('Y-m-d');
    }

    public function updatedTerm(): void
    {
        $this->examGroupId = '';
    }

    public function updatedClassId(): void
    {
        $this->examGroupId = '';
    }

    public function generate(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'academicYear' => ['required', 'string', 'max:9'],
            'term' => ['required', 'integer', 'between:1,3'],
            'examGroupId' => ['required', 'integer', 'exists:exams,id'],
            'startDate' => ['required', 'date'],
        ]);

        $master = Exam::with(['groupedSubjects.learningArea', 'learningArea', 'schoolClass'])
            ->whereNull('exam_group_id')
            ->whereKey((int) $this->examGroupId)
            ->where('academic_year', $this->academicYear)
            ->where('term', (string) $this->term)
            ->firstOrFail();
        $subjects = collect([$master])->merge($master->groupedSubjects)->values();
        if ($subjects->isEmpty()) {
            throw ValidationException::withMessages(['examGroupId' => 'This exam has no subjects to schedule.']);
        }

        $allocations = TeacherSubjectAllocation::where('academic_year', $this->academicYear)
            ->where('term', $this->term)->where('class_id', $master->class_id)->where('is_active', true)
            ->get()->keyBy('learning_area_id');
        $fallbackInvigilator = $master->created_by ?: StaffMember::query()->teaching()->where('is_active', true)->value('id');
        if (! $fallbackInvigilator) {
            throw ValidationException::withMessages(['examGroupId' => 'Add at least one active teaching staff member before generating an exam timetable.']);
        }

        $groupExamIds = $master->groupExamIds();
        $existing = ExamTimetable::query()->whereNotIn('exam_id', $groupExamIds)->where('date', '>=', $this->startDate)->get();
        $occupied = [];
        foreach ($existing as $slot) {
            $occupied['class|' . $slot->class_id . '|' . $slot->date . '|' . $slot->start_time] = true;
            $occupied['teacher|' . $slot->invigilator_id . '|' . $slot->date . '|' . $slot->start_time] = true;
            if ($slot->venue) $occupied['venue|' . $slot->venue . '|' . $slot->date . '|' . $slot->start_time] = true;
        }

        $rows = [];
        $cursor = Carbon::parse($this->startDate)->startOfDay();
        $period = 0;
        foreach ($subjects as $subject) {
            while ($cursor->isWeekend()) $cursor->addDay();
            $allocation = $allocations->get($subject->learning_area_id);
            $invigilatorId = (int) ($allocation?->teacher_id ?: $fallbackInvigilator);
            $time = self::TIMES[$period];
            $date = $cursor->toDateString();
            $venue = $master->schoolClass?->name ?: 'Main examination room';
            if (isset($occupied['class|' . $master->class_id . '|' . $date . '|' . $time[0]])
                || isset($occupied['teacher|' . $invigilatorId . '|' . $date . '|' . $time[0]])
                || isset($occupied['venue|' . $venue . '|' . $date . '|' . $time[0]])) {
                throw ValidationException::withMessages(['examGroupId' => "A class, invigilator, or venue conflict exists on {$date} at {$time[0]}. Clear the conflicting timetable first."]);
            }
            $rows[] = [
                'exam_id' => $subject->id, 'class_id' => $master->class_id, 'invigilator_id' => $invigilatorId,
                'venue' => $venue, 'date' => $date, 'start_time' => $time[0], 'end_time' => $time[1],
                'is_published' => false, 'created_at' => now(), 'updated_at' => now(),
            ];
            $occupied['class|' . $master->class_id . '|' . $date . '|' . $time[0]] = true;
            $occupied['teacher|' . $invigilatorId . '|' . $date . '|' . $time[0]] = true;
            $occupied['venue|' . $venue . '|' . $date . '|' . $time[0]] = true;
            $period++;
            if ($period >= count(self::TIMES)) {
                $period = 0;
                $cursor->addDay();
            }
        }

        DB::transaction(function () use ($master, $rows): void {
            $groupExamIds = $master->groupExamIds();
            ExamTimetable::whereIn('exam_id', $groupExamIds)->delete();
            ExamTimetable::insert($rows);
            foreach ($rows as $row) {
                Exam::whereKey($row['exam_id'])->update(['exam_date' => $row['date'], 'start_time' => $row['start_time']]);
            }
        });
        $this->notice = count($rows) . ' exam paper(s) scheduled as a draft. Review the timetable, then publish it.';
    }

    public function publish(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate(['examGroupId' => ['required', 'integer', 'exists:exams,id']]);
        $master = Exam::whereNull('exam_group_id')->findOrFail((int) $this->examGroupId);
        $count = ExamTimetable::whereIn('exam_id', $master->groupExamIds())->update(['is_published' => true]);
        $this->notice = $count ? "{$count} exam paper(s) published to teachers and classes." : 'Generate the exam timetable before publishing it.';
    }

    public function unpublish(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate(['examGroupId' => ['required', 'integer', 'exists:exams,id']]);
        $master = Exam::whereNull('exam_group_id')->findOrFail((int) $this->examGroupId);
        ExamTimetable::whereIn('exam_id', $master->groupExamIds())->update(['is_published' => false]);
        $this->notice = 'Exam timetable unpublished for editing.';
    }

    public function render()
    {
        $groups = Exam::with(['schoolClass', 'learningArea', 'groupedSubjects.learningArea'])
            ->whereNull('exam_group_id')->where('academic_year', $this->academicYear)
            ->where('term', (string) $this->term)->latest()->get();
        if ($this->classId !== '') {
            $groups = $groups->where('class_id', (int) $this->classId)->values();
        }
        $slots = ExamTimetable::with(['exam.learningArea', 'schoolClass', 'invigilator'])
            ->whereHas('exam', fn ($query) => $query->where('academic_year', $this->academicYear)->where('term', (string) $this->term))
            ->when($this->classId, fn ($query) => $query->where('class_id', (int) $this->classId))
            ->orderBy('date')->orderBy('start_time')->get();
        return view('livewire.admin.exam-timetable-manager', [
            'groups' => $groups,
            'classes' => \App\Models\SchoolClass::forConfiguredGrades()->where('is_active', true)
                ->where('academic_year', $this->academicYear)->orderBy('grade_level')->orderBy('name')->get(),
            'slots' => $slots,
        ])->layout('layouts.admin');
    }

    private function canManage(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal', 'deputy-headteacher', 'deputy']);
    }
}
