<?php

namespace App\Livewire\Exams;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\LearningArea;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\StaffMember;
use App\Models\TeacherSubjectAllocation;
use App\Models\GradingScale;
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
    public array   $selectedExamAreaIds = [];
    public string $examType       = 'end_term';
    public string $examTerm       = '';
    public string $termFilter     = '';
    public float  $totalMarks     = 100;
    public float  $passMark       = 50;
    public ?string $examDate      = null;
    public string $examScaleName  = '';
    public array $examScaleBands  = [];
    public array $reviewResults = [];
    public string $reviewComment = '';

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
        $this->termFilter = (string) config('school.current_term');
        $this->examDate = now()->format('Y-m-d');
    }

    public function updatedExamClassId($classId): void
    {
        $this->examAreaId = null;
        $this->selectedExamAreaIds = [];
        $this->resetErrorBag(['examClassId', 'examAreaId']);

        if (! $classId) {
            $this->examGrade = '';
            $this->examScaleName = '';
            $this->examScaleBands = [];
            return;
        }

        try {
            $class = SchoolClass::with('learningAreas')->findOrFail((int) $classId);
            $this->examGrade = (string) $class->grade_level;
            $scale = $class->gradingScales()
                ->wherePivot('academic_year', (string) config('school.academic_year'))
                ->where('grading_scales.is_active', true)
                ->orderByDesc('grading_scales.id')->first();
            $this->examScaleName = $scale?->name ?? 'No grading scale assigned';
            $this->examScaleBands = is_array($scale?->bands) ? $scale->bands : [];
            $areas = $class->learningAreas;
            $this->selectedExamAreaIds = $areas->pluck('id')->map(fn ($id) => (string) $id)->all();
            if ($areas->count() === 1) $this->examAreaId = (int) $areas->first()->id;
        } catch (\Throwable $exception) {
            report($exception);
            $this->examGrade = '';
            $this->examScaleName = '';
            $this->examScaleBands = [];
            $this->addError('examClassId', 'This class could not load its subjects. Check the class subject assignments and try again.');
        }
    }

    public function createExam(): void
    {
        $this->validate([
            'examName' => ['required', 'string', 'max:200'],
            'examGrade' => ['required'],
            'examClassId' => ['required', 'exists:school_classes,id'],
            'selectedExamAreaIds' => ['required', 'array', 'min:1'],
            'selectedExamAreaIds.*' => ['integer', 'exists:learning_areas,id'],
            'examType' => ['required'], 'examTerm' => ['required'],
            'totalMarks' => ['required', 'numeric', 'min:1', 'max:100'],
            'passMark' => ['required', 'numeric', 'min:0', 'lte:totalMarks'],
        ]);
        abort_unless($this->isFullAdmin() || auth()->user()->can('manage exams'), 403);
        $teacher = StaffMember::where('user_id', auth()->id())->first();
        $class = SchoolClass::findOrFail($this->examClassId);
        $gradingScale = $class->gradingScale()->first();
        if (! $gradingScale) {
            $this->addError('examClassId', 'Assign an active grading scale to this class before creating an exam.');
            return;
        }
        $classAreaIds = $class->learningAreas()->pluck('learning_areas.id')->map(fn ($id) => (int) $id);
        $selectedAreaIds = collect($this->selectedExamAreaIds)->map(fn ($id) => (int) $id)->unique()->values();
        if ($selectedAreaIds->diff($classAreaIds)->isNotEmpty()) {
            $this->addError('selectedExamAreaIds', 'Every selected subject must be assigned to the selected class first.');
            return;
        }
        if (!$this->isFullAdmin()) {
            $allocated = TeacherSubjectAllocation::where('teacher_id', $teacher?->id)
                ->where('class_id', $this->examClassId)->whereIn('learning_area_id', $selectedAreaIds)
                ->where('academic_year', config('school.academic_year'))->where('term', (int) $this->examTerm)
                ->where('is_active', true)->pluck('learning_area_id')->map(fn ($id) => (int) $id);
            if ($selectedAreaIds->diff($allocated)->isNotEmpty()) {
                $this->addError('selectedExamAreaIds', 'You are not allocated to every selected subject for this term.');
                return;
            }
        }

        $attributes = [
            'name' => $this->examName, 'grade_level' => $this->examGrade, 'class_id' => $this->examClassId,
            'academic_year' => config('school.academic_year'), 'term' => $this->examTerm,
            'exam_type' => $this->examType, 'total_marks' => $this->totalMarks, 'pass_mark' => $this->passMark,
            'exam_date' => $this->examDate, 'status' => 'published',
        ];

        if ($this->editingExamId) {
            $exam = Exam::findOrFail($this->editingExamId);
            abort_unless($this->isFullAdmin(), 403);
            abort_if($exam->isLocked(), 422, 'Locked exam results cannot be edited.');
            $exam->update($attributes + ['learning_area_id' => $selectedAreaIds->first()]);
            $message = 'Exam updated successfully.';
        } else {
            foreach ($selectedAreaIds as $areaId) {
                Exam::create($attributes + ['learning_area_id' => $areaId, 'created_by' => $teacher?->id ?? 1]);
            }
            $message = $selectedAreaIds->count() . ' exam subject(s) created successfully.';
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
        $this->selectedExamAreaIds = [(string) $exam->learning_area_id];
        $scale = $exam->schoolClass?->gradingScale;
        $this->examScaleName = $scale?->name ?? 'No grading scale assigned';
        $this->examScaleBands = $scale?->bands ?? [];
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
        $this->reset(['examName','examGrade','examClassId','examAreaId','selectedExamAreaIds','examTerm','examDate','examScaleName','examScaleBands']);
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
        abort_unless(in_array($exam->marks_status, ['draft', 'returned'], true) && ! $exam->isLocked(), 422, 'These marks are already submitted or published.');
        if (!$this->isFullAdmin() && !TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
            ->where('class_id', $exam->class_id)->where('learning_area_id', $exam->learning_area_id)
            ->where('academic_year', $exam->academic_year)->where('term', (int) $exam->term)
            ->where('is_active', true)->exists()) {
            abort(403, 'You are not allocated to this exam class and subject.');
        }
        $teacher = StaffMember::where('user_id', auth()->id())->first();
        $gradingScale = $exam->schoolClass?->gradingScale()->first();
        if (! $gradingScale) {
            $this->addError('marks', 'This class has no active grading scale. Ask an administrator to assign one first.');
            return;
        }
        $saved   = 0;

        $learnerIds = Learner::where('class_id', $exam->class_id)->where('grade_level', $exam->grade_level)->where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id);
        $missing = $learnerIds->filter(fn ($id) => !isset($this->marks[$id]['marks']) || $this->marks[$id]['marks'] === '')->values();
        if ($missing->isNotEmpty()) {
            $this->addError('marks', 'Every learner must have marks before submitting this subject.');
            return;
        }

        foreach ($this->marks as $learnerId => $data) {
            if ($data['marks'] === '' || $data['marks'] === null) continue;

            $marks = (float) $data['marks'];
            if ($marks < 0 || $marks > 100 || $marks > (float) $exam->total_marks) {
                $this->addError('marks', "Marks must be between 0 and 100 and cannot exceed the exam total of {$exam->total_marks}.");
                return;
            }
            $grade = $this->calculateGrade($marks, $exam->total_marks, $gradingScale);

            ExamResult::updateOrCreate(
                ['exam_id' => $exam->id, 'learner_id' => $learnerId],
                [
                    'marks_obtained' => $marks,
                    'total_marks'    => $exam->total_marks,
                    'grade'          => $grade,
                    'rubric_level'   => $this->calculateRubric(($marks / (float) $exam->total_marks) * 100, $gradingScale),
                    'remarks'        => $gradingScale->commentForCode($grade),
                    'marked_by'      => $teacher?->id ?? 1,
                ]
            );
            $saved++;
        }

        $exam->update([
            'marks_status' => 'submitted', 'marks_submitted_at' => now(),
            'marks_submitted_by' => auth()->id(), 'marks_review_comment' => null,
        ]);

        $this->dispatch('notify', type: 'success', message: "{$saved} marks submitted for review.");
    }

    public function openMarksReview(int $examId): void
    {
        abort_unless($this->canReviewMarks(), 403);
        $exam = Exam::with(['results.learner'])->findOrFail($examId);
        abort_unless($exam->marks_status === 'submitted', 422, 'Only submitted marks can be reviewed.');
        $this->selectedExam = $exam->id;
        $this->reviewResults = $exam->results->map(fn ($result) => [
            'name' => $result->learner?->full_name ?? 'Unknown learner',
            'admission_number' => $result->learner?->admission_number ?? '-',
            'marks' => $result->marks_obtained,
            'total' => $result->total_marks,
            'grade' => $result->grade,
            'rubric_level' => $result->rubric_level?->value ?? $result->rubric_level,
            'remarks' => $result->remarks,
        ])->values()->all();
        $this->reviewComment = '';
        $this->tab = 'review';
    }

    public function returnMarksForCorrection(): void
    {
        abort_unless($this->canReviewMarks(), 403);
        $exam = Exam::findOrFail($this->selectedExam);
        abort_unless($exam->marks_status === 'submitted', 422, 'Only submitted marks can be returned.');
        $exam->update(['marks_status' => 'returned', 'marks_reviewed_at' => now(), 'marks_reviewed_by' => auth()->id(), 'marks_review_comment' => $this->reviewComment ?: 'Please correct and resubmit the marks.']);
        $this->tab = 'exams';
        session()->flash('success', 'Marks returned to the teacher for correction and resubmission.');
    }

    public function approveMarksAndPublish(): void
    {
        abort_unless($this->canReviewMarks(), 403);
        $exam = Exam::with('results')->findOrFail($this->selectedExam);
        $learnerCount = Learner::where('class_id', $exam->class_id)->where('grade_level', $exam->grade_level)->where('is_active', true)->count();
        abort_unless($learnerCount > 0 && $exam->marks_status === 'submitted' && $exam->results->count() === $learnerCount && $exam->results->every(fn ($result) => $result->marks_obtained !== null), 422, 'All learner marks must be submitted before publishing results.');
        $exam->update(['marks_status' => 'approved', 'marks_reviewed_at' => now(), 'marks_reviewed_by' => auth()->id(), 'marks_review_comment' => $this->reviewComment ?: null, 'status' => 'completed', 'results_locked_at' => now(), 'locked_by' => auth()->user()->staffMember?->id]);
        $this->tab = 'exams';
        session()->flash('success', 'Marks approved and results published.');
    }

    public function lockResults(): void
    {
        $exam = Exam::findOrFail($this->selectedExam);
        abort_unless($exam->marks_status === 'approved', 422, 'Marks must be reviewed and approved before locking results.');
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
        abort_unless($exam->status === 'completed' && $exam->marks_status === 'approved', 422, 'Results can only be sent after all marks are reviewed and approved.');
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

    private function calculateGrade(float $marks, float $total, ?GradingScale $scale = null): string
    {
        $percent = ($marks / $total) * 100;
        if ($scale && ($grade = $scale->gradeForPercent($percent)) !== null) {
            return $grade;
        }
        return match(true) {
            $percent >= 80 => 'A',
            $percent >= 70 => 'B',
            $percent >= 60 => 'C',
            $percent >= 50 => 'D',
            default        => 'E',
        };
    }

    private function calculateRubric(float $percent, ?GradingScale $scale = null): \App\Enums\RubricLevel
    {
        $code = $scale?->gradeForPercent($percent);
        if ($code) {
            return match (substr($code, 0, 2)) {
                'EE' => \App\Enums\RubricLevel::ExceedsExpectation,
                'ME' => \App\Enums\RubricLevel::MeetsExpectation,
                'AE' => \App\Enums\RubricLevel::ApproachesExpectation,
                default => \App\Enums\RubricLevel::BelowExpectation,
            };
        }
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

    private function canReviewMarks(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal', 'deputy-principal']);
    }

    public function render()
    {
        $fullAdmin = $this->isFullAdmin();
        $allocation = TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
            ->where('academic_year', config('school.academic_year'))->where('is_active', true);
        $exams = Exam::with(['learningArea', 'schoolClass'])
            ->where('academic_year', config('school.academic_year'))
            ->where('term', (int) $this->termFilter)
            ->when(!$fullAdmin, fn ($query) => $query->whereIn('class_id', (clone $allocation)->pluck('class_id'))->whereIn('learning_area_id', (clone $allocation)->pluck('learning_area_id')))
            ->latest()->paginate(20);

        return view('livewire.exams.exam-manager', [
            'exams'         => $exams,
            'learningAreas' => $this->availableLearningAreas($fullAdmin, $allocation),
            'classes' => $fullAdmin ? SchoolClass::forConfiguredGrades()->with('learningAreas')->orderBy('grade_level')->get() : SchoolClass::forConfiguredGrades()->whereIn('id', (clone $allocation)->pluck('class_id'))->with('learningAreas')->orderBy('grade_level')->get(),
            'gradeLevels'   => config('school.grade_levels'),
            'marks'         => $this->marks,
            'examScaleName' => $this->examScaleName,
            'examScaleBands' => $this->examScaleBands,
            'reviewExam' => $this->selectedExam ? Exam::with(['schoolClass', 'learningArea'])->find($this->selectedExam) : null,
        ])->layout($fullAdmin ? 'layouts.admin' : 'layouts.teacher');
    }

    private function availableLearningAreas(bool $fullAdmin, $allocation)
    {
        if ($this->examClassId) {
            try {
                return SchoolClass::findOrFail((int) $this->examClassId)->learningAreas()
                    ->where('learning_areas.is_active', true)->orderBy('name')->get();
            } catch (\Throwable $exception) {
                report($exception);
                return collect();
            }
        }

        return $fullAdmin
            ? LearningArea::where('is_active', true)->orderBy('name')->get()
            : LearningArea::whereIn('id', (clone $allocation)->pluck('learning_area_id'))->where('is_active', true)->orderBy('name')->get();
    }
}
