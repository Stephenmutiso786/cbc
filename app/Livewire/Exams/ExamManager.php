<?php

namespace App\Livewire\Exams;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\LearningArea;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\StaffMember;
use App\Models\TeacherSubjectAllocation;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendExamResultsSmsJob;
use App\Models\SchoolNotification;

class ExamManager extends Component
{
    use WithPagination;

    public string $tab          = 'exams'; // exams | marks | results
    public ?int   $selectedExam = null;

    // Create Exam form
    public bool   $showCreateModal = false;
    public ?int   $editingExamId   = null;
    public string $examName       = '';
    public string $examGrade      = '';
    public string $examClassId    = '';
    public ?int   $examAreaId     = null;
    public string $examType       = 'end_term';
    public string $examTerm       = '';
    public float  $totalMarks     = 100;
    public float  $passMark       = 50;
    public ?string $examDate      = null;

    // Mark entry
    public array  $marks          = []; // keyed by learner_id

    protected $rules = [
        'examName'  => 'required|string|max:200',
        'examGrade' => 'required',
        'examClassId' => 'required|exists:school_classes,id',
        'examAreaId'=> 'required|exists:learning_areas,id',
        'examType'  => 'required',
        'examTerm'  => 'required',
        'totalMarks'=> 'required|numeric|min:1',
        'passMark'  => 'required|numeric|min:0',
    ];

    public function mount(): void
    {
        $this->examTerm = (string) config('school.current_term');
        $this->examDate = now()->format('Y-m-d');
    }

    public function updatedExamClassId($classId): void
    {
        $class = $classId ? SchoolClass::with('learningAreas')->find($classId) : null;
        $areas = $class?->learningAreas ?? collect();
        $this->examGrade = (string) ($class?->grade_level ?? '');

        if (!$areas->contains('id', $this->examAreaId)) {
            $this->examAreaId = $areas->count() === 1 ? (int) $areas->first()->id : null;
        }
    }

    public function createExam(): void
    {
        $this->validate();
        abort_unless($this->isFullAdmin() || auth()->user()->can('manage exams'), 403);
        $teacher = StaffMember::where('user_id', auth()->id())->first();
        if (!SchoolClass::findOrFail($this->examClassId)->learningAreas()->whereKey($this->examAreaId)->exists()) {
            $this->addError('examAreaId', 'Assign this subject to the selected class first in Classes.');
            return;
        }
        if (!$this->isFullAdmin() && !TeacherSubjectAllocation::where('teacher_id', $teacher?->id)
            ->where('class_id', $this->examClassId)->where('learning_area_id', $this->examAreaId)->where('academic_year', config('school.academic_year'))
            ->where('term', (int) $this->examTerm)->where('is_active', true)->exists()) {
            $this->addError('examAreaId', 'You are not allocated to this learning area for this term.');
            return;
        }

        $attributes = [
            'name'            => $this->examName,
            'grade_level'     => $this->examGrade,
            'class_id'        => $this->examClassId,
            'learning_area_id'=> $this->examAreaId,
            'academic_year'   => config('school.academic_year'),
            'term'            => $this->examTerm,
            'exam_type'       => $this->examType,
            'total_marks'     => $this->totalMarks,
            'pass_mark'       => $this->passMark,
            'exam_date'       => $this->examDate,
            'status'          => 'published',
        ];

        if ($this->editingExamId) {
            $exam = Exam::findOrFail($this->editingExamId);
            abort_unless($this->isFullAdmin(), 403);
            abort_if($exam->isLocked(), 422, 'Locked exam results cannot be edited.');
            $exam->update($attributes);
            $message = 'Exam updated successfully.';
        } else {
            $attributes['created_by'] = $teacher?->id ?? 1;
            Exam::create($attributes);
            $message = 'Exam created successfully.';
        }

        $this->dispatch('notify', type: 'success', message: $message);
        $this->closeExamForm();
    }

    public function editExam(int $examId): void
    {
        abort_unless($this->isFullAdmin(), 403);
        $exam = Exam::findOrFail($examId);
        abort_if($exam->isLocked(), 422, 'Locked exam results cannot be edited.');
        $this->editingExamId = $exam->id;
        $this->examName = $exam->name;
        $this->examGrade = $exam->grade_level;
        $this->examClassId = (string) $exam->class_id;
        $this->examAreaId = $exam->learning_area_id;
        $this->examType = $exam->exam_type;
        $this->examTerm = (string) $exam->term;
        $this->totalMarks = (float) $exam->total_marks;
        $this->passMark = (float) $exam->pass_mark;
        $this->examDate = $exam->exam_date?->format('Y-m-d');
        $this->showCreateModal = true;
    }

    public function deleteExam(int $examId): void
    {
        abort_unless($this->isFullAdmin(), 403);
        DB::transaction(fn () => Exam::findOrFail($examId)->delete());
        if ($this->selectedExam === $examId) {
            $this->selectedExam = null;
            $this->marks = [];
            $this->tab = 'exams';
        }
        session()->flash('success', 'Exam and its results were deleted successfully.');
    }

    private function closeExamForm(): void
    {
        $this->showCreateModal = false;
        $this->editingExamId = null;
        $this->reset(['examName','examGrade','examClassId','examAreaId','examTerm','examDate']);
        $this->examTerm = (string) config('school.current_term');
        $this->examDate = now()->format('Y-m-d');
    }

    public function loadMarkEntry(int $examId): void
    {
        $this->selectedExam = $examId;
        $exam = Exam::findOrFail($examId);
        if (!$this->isFullAdmin() && !TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
            ->where('class_id', $exam->class_id)->where('learning_area_id', $exam->learning_area_id)->where('academic_year', $exam->academic_year)
            ->where('term', (int) $exam->term)->where('is_active', true)->exists()) {
            abort(403, 'You are not allocated to this exam subject.');
        }
        $learners = Learner::when($exam->class_id, fn ($query) => $query->where('class_id', $exam->class_id))
            ->where('grade_level', $exam->grade_level)->where('is_active', true)->get();

        $existing = ExamResult::where('exam_id', $examId)->pluck('marks_obtained', 'learner_id');

        $this->marks = $learners->mapWithKeys(fn($l) => [
            $l->id => ['name' => $l->full_name, 'marks' => $existing[$l->id] ?? '']
        ])->toArray();

        $this->tab = 'marks';
    }

    public function saveMarks(): void
    {
        $exam    = Exam::findOrFail($this->selectedExam);
        if (!$this->isFullAdmin() && !TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
            ->where('class_id', $exam->class_id)->where('learning_area_id', $exam->learning_area_id)
            ->where('academic_year', $exam->academic_year)->where('term', (int) $exam->term)
            ->where('is_active', true)->exists()) {
            abort(403, 'You are not allocated to this exam class and subject.');
        }
        $teacher = StaffMember::where('user_id', auth()->id())->first();
        $saved   = 0;

        foreach ($this->marks as $learnerId => $data) {
            if ($data['marks'] === '' || $data['marks'] === null) continue;

            $marks = (float) $data['marks'];
            $grade = $this->calculateGrade($marks, $exam->total_marks);

            ExamResult::updateOrCreate(
                ['exam_id' => $exam->id, 'learner_id' => $learnerId],
                [
                    'marks_obtained' => $marks,
                    'total_marks'    => $exam->total_marks,
                    'grade'          => $grade,
                    'rubric_level'   => $this->calculateRubric(($marks / (float) $exam->total_marks) * 100),
                    'marked_by'      => $teacher?->id ?? 1,
                ]
            );
            $saved++;
        }

        $this->dispatch('notify', type: 'success', message: "{$saved} results saved.");
    }

    public function lockResults(): void
    {
        $exam = Exam::findOrFail($this->selectedExam);
        abort_unless(auth()->user()->can('lockResults', $exam), 403);
        $exam->update([
            'status' => 'completed',
            'results_locked_at' => now(),
            'locked_by' => auth()->user()->staffMember?->id,
        ]);
        $this->tab = 'exams';
        session()->flash('success', 'Results locked. Further mark changes are disabled.');
    }

    public function sendResults(int $examId): void
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal']), 403);

        $exam = Exam::with(['results.learner.guardians'])->findOrFail($examId);
        abort_unless(in_array($exam->status, ['published', 'completed'], true), 422, 'Only published or completed exams can send results.');
        abort_if($exam->results_sms_status === 'queued', 422, 'Results are already being sent.');
        abort_if($exam->results_sms_status === 'sent', 422, 'Results have already been sent for this exam.');

        $recipients = $exam->results->flatMap(fn ($result) => $result->learner?->guardians ?? collect())->whereNotNull('phone_number');
        abort_if($recipients->isEmpty(), 422, 'No result guardians have phone numbers.');

        $staff = StaffMember::where('user_id', auth()->id())->first();
        abort_unless($staff, 422, 'This account is not linked to a staff profile.');

        $notification = SchoolNotification::create([
            'sender_id' => $staff->id,
            'title' => 'Exam results: ' . $exam->name,
            'message' => 'Individual learner results sent by SMS.',
            'type' => 'exam',
            'channel' => 'sms',
            'total_recipients' => $recipients->count(),
            'status' => 'queued',
            'scheduled_at' => now(),
        ]);

        $exam->update(['results_sms_status' => 'queued', 'results_sms_queued_at' => now()]);
        SendExamResultsSmsJob::dispatch($exam->id, $notification->id);
        session()->flash('success', "Exam results queued for {$recipients->count()} guardian phone number(s).");
    }

    private function calculateGrade(float $marks, float $total): string
    {
        $percent = ($marks / $total) * 100;
        return match(true) {
            $percent >= 80 => 'A',
            $percent >= 70 => 'B',
            $percent >= 60 => 'C',
            $percent >= 50 => 'D',
            default        => 'E',
        };
    }

    private function calculateRubric(float $percent): \App\Enums\RubricLevel
    {
        return match (true) {
            $percent >= 75 => \App\Enums\RubricLevel::ExceedsExpectation,
            $percent >= 50 => \App\Enums\RubricLevel::MeetsExpectation,
            $percent >= 30 => \App\Enums\RubricLevel::ApproachesExpectation,
            default => \App\Enums\RubricLevel::BelowExpectation,
        };
    }

    private function isFullAdmin(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super-admin']);
    }

    public function render()
    {
        $fullAdmin = $this->isFullAdmin();
        $allocation = TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
            ->where('academic_year', config('school.academic_year'))->where('is_active', true);
        $exams = Exam::with(['learningArea', 'schoolClass'])
            ->where('academic_year', config('school.academic_year'))
            ->when(!$fullAdmin, fn ($query) => $query->whereIn('class_id', (clone $allocation)->pluck('class_id'))->whereIn('learning_area_id', (clone $allocation)->pluck('learning_area_id')))
            ->latest()->paginate(20);

        return view('livewire.exams.exam-manager', [
            'exams'         => $exams,
            'learningAreas' => $this->availableLearningAreas($fullAdmin, $allocation),
            'classes' => $fullAdmin ? SchoolClass::forConfiguredGrades()->with('learningAreas')->orderBy('grade_level')->get() : SchoolClass::forConfiguredGrades()->whereIn('id', (clone $allocation)->pluck('class_id'))->with('learningAreas')->orderBy('grade_level')->get(),
            'gradeLevels'   => config('school.grade_levels'),
            'marks'         => $this->marks,
        ])->layout('layouts.admin');
    }

    private function availableLearningAreas(bool $fullAdmin, $allocation)
    {
        if ($this->examClassId) {
            return SchoolClass::find($this->examClassId)?->learningAreas()->where('learning_areas.is_active', true)->orderBy('name')->get()
                ?? collect();
        }

        return $fullAdmin
            ? LearningArea::where('is_active', true)->orderBy('name')->get()
            : LearningArea::whereIn('id', (clone $allocation)->pluck('learning_area_id'))->where('is_active', true)->orderBy('name')->get();
    }
}
