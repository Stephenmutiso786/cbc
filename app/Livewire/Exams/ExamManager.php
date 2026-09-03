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
use Throwable;

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
    public array  $markSubjectOptions = [];
    public float $marksTotal = 100;
    public string $marksEntryStatus = 'draft';

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
            'exam_date' => $this->examDate, 'status' => 'draft', 'exam_state' => 'draft',
        ];

        if ($this->editingExamId) {
            $exam = Exam::findOrFail($this->editingExamId);
            abort_unless($this->isFullAdmin(), 403);
            abort_if($exam->isLocked(), 422, 'Locked exam results cannot be edited.');
            $exam->update($attributes + ['learning_area_id' => $selectedAreaIds->first()]);
            $message = 'Exam updated successfully.';
        } else {
            $master = Exam::create($attributes + ['learning_area_id' => $selectedAreaIds->first(), 'created_by' => $teacher?->id ?? 1]);
            foreach ($selectedAreaIds->skip(1) as $areaId) {
                Exam::create($attributes + ['exam_group_id' => $master->id, 'learning_area_id' => $areaId, 'created_by' => $teacher?->id ?? 1]);
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
        $exam = Exam::findOrFail($examId);
        $groupIds = $exam->groupExamIds();
        DB::transaction(fn () => Exam::whereIn('id', $groupIds)->delete());
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

    public function loadMarkEntry(int $examId, bool $subjectAlreadyChosen = false): void
    {
        $this->selectedExam = $examId;
        $exam = Exam::with(['learningArea', 'groupedSubjects.learningArea'])->findOrFail($examId);
        if (! $subjectAlreadyChosen && $exam->isGroupMaster() && $exam->groupedSubjects->isNotEmpty()) {
            $subjects = collect([$exam])->merge($exam->groupedSubjects);
            if (! $this->isFullAdmin()) {
                $subjects = $subjects->filter(fn ($subject) => TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
                    ->where('class_id', $subject->class_id)->where('learning_area_id', $subject->learning_area_id)
                    ->where('academic_year', $subject->academic_year)->where('term', (int) $subject->term)
                    ->where('is_active', true)->exists());
            }
            $this->markSubjectOptions = $subjects->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->learningArea?->name ?? 'Learning area',
                'status' => $subject->marks_status,
            ])->values()->all();
            if (count($this->markSubjectOptions) > 1) {
                $this->tab = 'subject-select';
                return;
            }
            if (count($this->markSubjectOptions) === 1) $examId = $this->markSubjectOptions[0]['id'];
            $exam = Exam::with(['learningArea', 'groupedSubjects.learningArea'])->findOrFail($examId);
        }
        abort_unless(in_array($exam->marks_status, ['draft', 'returned'], true) && ! $exam->isLocked(), 422, 'These marks are already submitted or published.');
        if (!$this->isFullAdmin() && !TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
            ->where('class_id', $exam->class_id)->where('learning_area_id', $exam->learning_area_id)->where('academic_year', $exam->academic_year)
            ->where('term', (int) $exam->term)->where('is_active', true)->exists()) {
            abort(403, 'You are not allocated to this exam subject.');
        }
        $this->examScaleBands = $exam->schoolClass?->gradingScale()->first()?->bands ?? [];
        $this->marksTotal = (float) $exam->total_marks;
        $learners = Learner::when($exam->class_id, fn ($query) => $query->where('class_id', $exam->class_id))
            ->where('grade_level', $exam->grade_level)->where('is_active', true)->get();

        $existing = ExamResult::where('exam_id', $examId)->get()->keyBy('learner_id');

        $this->marks = $learners->mapWithKeys(fn($l) => [
            $l->id => [
                'name' => $l->full_name,
                'marks' => $existing[$l->id]?->marks_obtained ?? '',
                'rubric' => $existing[$l->id]?->rubric_level?->value ?? '-',
            ]
        ])->toArray();

        $this->marksEntryStatus = $exam->marks_status;
        $this->tab = 'marks';
    }

    public function chooseMarkSubject(int $examId): void
    {
        $this->loadMarkEntry($examId, true);
    }

    public function saveMarks(): void
    {
        $exam = $this->editableMarksExam();
        $saved = $this->persistMarks($exam);
        if ($saved === null) return;

        $this->dispatch('notify', type: 'success', message: "{$saved} learner mark entries saved as draft. Submit them when complete.");
    }

    public function submitMarks(): void
    {
        $exam = $this->editableMarksExam();
        try {
            $saved = null;
            DB::transaction(function () use ($exam, &$saved): void {
                $saved = $this->persistMarks($exam);
                if ($saved !== null) {
                    $updated = Exam::whereKey($exam->id)
                        ->whereIn('marks_status', ['draft', 'returned'])
                        ->whereNull('results_locked_at')
                        ->update([
                            'marks_status' => 'submitted', 'marks_submitted_at' => now(),
                            'marks_submitted_by' => auth()->id(), 'marks_review_comment' => null,
                        ]);

                    if ($updated !== 1) {
                        throw new \RuntimeException('The marks were changed by another request before submission.');
                    }
                }
            });
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('marks', 'Marks could not be submitted. Nothing was locked; please try again.');
            return;
        }
        if ($saved === null) return;

        $this->marksEntryStatus = 'submitted';
        $this->marks = [];
        $this->selectedExam = null;
        $this->tab = 'exams';
        session()->flash('success', "{$saved} learner mark entries submitted successfully and locked. They cannot be edited until returned for correction.");
    }

    public function updatedMarks($value, $key): void
    {
        $learnerId = explode('.', (string) $key)[0];
        if (! isset($this->marks[$learnerId])) return;

        $raw = is_scalar($value) ? trim((string) $value) : '';
        if ($raw === '' || ! preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            $this->marks[$learnerId]['rubric'] = '-';
            return;
        }

        $exam = $this->selectedExam ? Exam::with('schoolClass')->find($this->selectedExam) : null;
        $scale = $exam?->schoolClass?->gradingScale()->first();
        $marks = (float) $raw;
        if (! $exam || $marks > 100 || $marks > (float) $exam->total_marks) {
            $this->marks[$learnerId]['rubric'] = '-';
            return;
        }

        $this->marks[$learnerId]['rubric'] = $this->calculateRubric(($marks / (float) $exam->total_marks) * 100, $scale)->value;
    }

    private function editableMarksExam(): Exam
    {
        $exam = Exam::findOrFail($this->selectedExam);
        abort_unless(in_array($exam->marks_status, ['draft', 'returned'], true) && ! $exam->isLocked(), 422, 'These marks are already submitted or published.');
        if (! $this->isFullAdmin() && ! TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
            ->where('class_id', $exam->class_id)->where('learning_area_id', $exam->learning_area_id)
            ->where('academic_year', $exam->academic_year)->where('term', (int) $exam->term)
            ->where('is_active', true)->exists()) {
            abort(403, 'You are not allocated to this exam class and subject.');
        }

        return $exam;
    }

    private function persistMarks(Exam $exam): ?int
    {
        $gradingScale = $exam->schoolClass?->gradingScale()->first();
        if (! $gradingScale) {
            $this->addError('marks', 'This class has no active grading scale. Ask an administrator to assign one first.');
            return null;
        }

        $learnerIds = Learner::where('class_id', $exam->class_id)
            ->where('grade_level', $exam->grade_level)->where('is_active', true)->pluck('id');
        if ($learnerIds->isEmpty()) {
            $this->addError('marks', 'No active learners were found for this class.');
            return null;
        }

        $normalized = [];
        foreach ($learnerIds as $learnerId) {
            $data = $this->marks[$learnerId] ?? $this->marks[(string) $learnerId] ?? [];
            $raw = $data['marks'] ?? '';
            if ($raw === '' || $raw === null) {
                $normalized[$learnerId] = null;
                continue;
            }
            if (! is_scalar($raw) || ! preg_match('/^\d+(?:\.\d+)?$/', trim((string) $raw))) {
                $this->addError('marks', 'Marks must contain numbers only, or be left blank for a learner who did not sit the exam.');
                return null;
            }
            $marks = (float) trim((string) $raw);
            if ($marks < 0 || $marks > 100 || $marks > (float) $exam->total_marks) {
                $this->addError('marks', "Marks must be between 0 and 100 and cannot exceed the exam total of {$exam->total_marks}.");
                return null;
            }
            $normalized[$learnerId] = $marks;
        }

        $teacher = StaffMember::where('user_id', auth()->id())->first();
        DB::transaction(function () use ($normalized, $exam, $gradingScale, $teacher): void {
            foreach ($normalized as $learnerId => $marks) {
                $grade = $marks === null ? null : $this->calculateGrade($marks, (float) $exam->total_marks, $gradingScale);
                ExamResult::updateOrCreate(
                    ['exam_id' => $exam->id, 'learner_id' => $learnerId],
                    [
                        'marks_obtained' => $marks,
                        'total_marks' => $exam->total_marks,
                        'grade' => $grade,
                        'rubric_level' => $marks === null ? null : $this->calculateRubric(($marks / (float) $exam->total_marks) * 100, $gradingScale),
                        'remarks' => $grade ? $gradingScale->commentForCode($grade) : null,
                        'marked_by' => $teacher?->id ?? 1,
                    ]
                );
            }
        });

        return count($normalized);
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

    public function openMarksReviewQueue(): void
    {
        abort_unless($this->canReviewMarks(), 403);
        $this->selectedExam = null;
        $this->reviewResults = [];
        $this->reviewComment = '';
        $this->tab = 'review-queue';
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
        $learnerIds = Learner::where('class_id', $exam->class_id)->where('grade_level', $exam->grade_level)->where('is_active', true)->pluck('id');
        $submittedCount = $exam->results->whereIn('learner_id', $learnerIds)->count();
        abort_unless($learnerIds->isNotEmpty() && $exam->marks_status === 'submitted' && $submittedCount === $learnerIds->count(), 422, 'Every learner must have a saved mark entry before publishing results. Blank entries are treated as did not sit.');
        $exam->update(['marks_status' => 'approved', 'marks_reviewed_at' => now(), 'marks_reviewed_by' => auth()->id(), 'marks_review_comment' => $this->reviewComment ?: null, 'status' => 'completed', 'results_locked_at' => now(), 'locked_by' => auth()->user()->staffMember?->id]);
        $this->tab = 'exams';
        session()->flash('success', 'Marks approved. Finalize the complete exam before publishing results.');
    }

    public function finalizeExam(int $examId): void
    {
        abort_unless($this->canReviewMarks(), 403);
        $exam = Exam::findOrFail($examId);
        $group = Exam::whereIn('id', $exam->groupExamIds())->get();
        abort_unless($group->isNotEmpty() && $group->every(fn ($item) => $item->marks_status === 'approved'), 422, 'Every subject must be submitted and reviewed before the exam can be finalized.');
        abort_unless($group->every(fn ($item) => $item->exam_state === 'draft'), 422, 'This exam has already been finalized or published.');

        DB::transaction(function () use ($group): void {
            Exam::whereIn('id', $group->pluck('id'))->update([
                'exam_state' => 'finalized',
                'results_locked_at' => now(),
                'locked_by' => auth()->user()->staffMember?->id,
            ]);
        });
        $this->tab = 'exams';
        session()->flash('success', 'All subjects were finalized. The exam is locked and ready for publication.');
    }

    public function publishExam(int $examId): void
    {
        abort_unless($this->canReviewMarks(), 403);
        $exam = Exam::findOrFail($examId);
        $group = Exam::whereIn('id', $exam->groupExamIds())->get();
        abort_unless($group->isNotEmpty() && $group->every(fn ($item) => $item->exam_state === 'finalized' && $item->marks_status === 'approved'), 422, 'Finalize the complete reviewed exam before publishing results.');

        DB::transaction(function () use ($group): void {
            Exam::whereIn('id', $group->pluck('id'))->update([
                'exam_state' => 'published',
                'status' => 'published',
            ]);
        });
        $this->tab = 'exams';
        session()->flash('success', 'Exam published. Report cards, merit lists, and result SMS are now available.');
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

        $exam = Exam::findOrFail($examId);
        $group = Exam::whereIn('id', $exam->groupExamIds())->get();
        if ($group->isEmpty() || ! $group->every(fn (Exam $item) => $item->isFullyPublished())) {
            $this->addError('results', 'Results can only be sent after every subject is reviewed and the complete exam is published.');
            return;
        }
        if ($exam->results_sms_status === 'queued') {
            $this->addError('results', 'Results are already being sent.');
            return;
        }
        $sendCount = (int) ($exam->results_sms_send_count ?? 0);
        if ($sendCount >= 3) {
            $this->addError('results', 'Results SMS sending is limited to three attempts for this exam.');
            return;
        }

        $recipients = ExamResult::with('learner.guardians')
            ->whereIn('exam_id', $exam->groupExamIds())
            ->get()
            ->flatMap(fn ($result) => $result->learner?->guardians ?? collect())
            ->whereNotNull('phone_number')->filter(fn ($guardian) => trim((string) $guardian->phone_number) !== '')
            ->unique('id')
            ->values();
        if ($recipients->isEmpty()) {
            $this->addError('results', 'No linked parent or guardian has a valid phone number for these learners.');
            return;
        }

        $staff = StaffMember::where('user_id', auth()->id())->first();
        if (! $staff) {
            $this->addError('results', 'This account is not linked to a staff profile.');
            return;
        }

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

        // Reserve the attempt atomically so two rapid requests cannot exceed
        // the three-send limit before either request refreshes the exam.
        $reserved = Exam::whereKey($exam->id)
            ->whereRaw('COALESCE(results_sms_send_count, 0) < 3')
            ->where(function ($query): void {
                $query->whereNull('results_sms_status')->orWhere('results_sms_status', '!=', 'queued');
            })
            ->update([
                'results_sms_status' => 'queued',
                'results_sms_send_count' => DB::raw('COALESCE(results_sms_send_count, 0) + 1'),
                'results_sms_queued_at' => now(),
            ]);

        if ($reserved !== 1) {
            $notification->update(['status' => 'failed', 'failed_count' => $recipients->count()]);
            $this->addError('results', 'Results SMS has reached its three-attempt limit or is already being sent.');
            return;
        }

        try {
            SendExamResultsSmsJob::dispatch($exam->id, $notification->id);
        } catch (Throwable $exception) {
            // A sync queue executes the provider call during this request. Do
            // not leave a failed send looking queued or turn it into a generic
            // Livewire error page.
            $exam->update(['results_sms_status' => 'failed']);
            $notification->update(['status' => 'failed', 'failed_count' => $recipients->count()]);
            report($exception);
            $this->addError('results', 'Olympus could not send the results: ' . $exception->getMessage());
            return;
        }
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

    public function canReviewMarks(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal', 'deputy-headteacher', 'deputy']);
    }

    public function render()
    {
        $fullAdmin = $this->isFullAdmin();
        $allocation = TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
            ->where('academic_year', config('school.academic_year'))->where('is_active', true);
        $allocatedExamIds = $fullAdmin ? collect() : Exam::where('academic_year', config('school.academic_year'))
            ->where('term', (int) $this->termFilter)
            ->whereIn('class_id', (clone $allocation)->pluck('class_id'))
            ->whereIn('learning_area_id', (clone $allocation)->pluck('learning_area_id'))
            ->pluck('id');
        $allocatedGroupMasterIds = $fullAdmin ? collect() : Exam::whereIn('id', $allocatedExamIds)
            ->pluck('exam_group_id')->filter()->values();
        $exams = Exam::with(['learningArea', 'schoolClass', 'results', 'groupedSubjects.learningArea', 'groupedSubjects.results'])
            ->whereNull('exam_group_id')
            ->where('academic_year', config('school.academic_year'))
            ->where('term', (int) $this->termFilter)
            ->when(!$fullAdmin, fn ($query) => $query->where(function ($query) use ($allocatedExamIds, $allocatedGroupMasterIds): void {
                $query->whereIn('id', $allocatedExamIds)->orWhereIn('id', $allocatedGroupMasterIds);
            }))
            ->latest()->paginate(20);
        $exams->getCollection()->each(function ($exam): void {
            $areas = collect([$exam->learningArea])->merge($exam->groupedSubjects->pluck('learningArea'))->filter();
            $exam->setRelation('learningArea', (object) ['name' => $areas->pluck('name')->unique()->join(', ')]);
            $subjects = collect([$exam])->merge($exam->groupedSubjects);
            $exam->setAttribute('subjects_total', $subjects->count());
            $exam->setAttribute('subjects_submitted', $subjects->whereIn('marks_status', ['submitted', 'approved'])->count());
            $exam->setAttribute('subjects_approved', $subjects->where('marks_status', 'approved')->count());
            $exam->setAttribute('subjects_awaiting_review', $subjects->where('marks_status', 'submitted')->count());
            // A published parent can never re-enter the finalization step,
            // even when an older record has inconsistent workflow columns.
            $exam->setAttribute('all_subjects_approved', $exam->status !== 'published'
                && $subjects->every(fn ($subject) => $subject->marks_status === 'approved'));
            $exam->setAttribute('all_subjects_published', $subjects->every(fn ($subject) => $subject->isFullyPublished()));
            $exam->setAttribute('all_subjects_finalized', $subjects->every(fn ($subject) => in_array($subject->exam_state, ['finalized', 'published'], true)));
            // The grouped row represents the complete exam, so expose all subject results to the view.
            $exam->setRelation('results', $subjects->flatMap(fn ($subject) => $subject->results)->values());
            $exam->setAttribute('has_editable_subjects', $subjects->contains(fn ($subject) => in_array($subject->marks_status, ['draft', 'returned'], true) && ! $subject->isLocked()));
            $exam->setAttribute('missing_subjects', $subjects->filter(fn ($subject) => in_array($subject->marks_status, ['draft', 'returned'], true))->map(fn ($subject) => $subject->learningArea?->name)->filter()->values()->all());
        });

        $reviewQueue = $this->canReviewMarks()
            ? Exam::with(['learningArea', 'schoolClass'])
                ->where('academic_year', config('school.academic_year'))
                ->where('term', (int) $this->termFilter)
                ->where('marks_status', 'submitted')
                ->latest('marks_submitted_at')->latest('id')->get()
            : collect();

        return view('livewire.exams.exam-manager', [
            'exams'         => $exams,
            'learningAreas' => $this->availableLearningAreas($fullAdmin, $allocation),
            'classes' => $fullAdmin ? SchoolClass::forConfiguredGrades()->with('learningAreas')->orderBy('grade_level')->get() : SchoolClass::forConfiguredGrades()->whereIn('id', (clone $allocation)->pluck('class_id'))->with('learningAreas')->orderBy('grade_level')->get(),
            'gradeLevels'   => config('school.grade_levels'),
            'marks'         => $this->marks,
            'examScaleName' => $this->examScaleName,
            'examScaleBands' => $this->examScaleBands,
            'reviewExam' => $this->selectedExam ? Exam::with(['schoolClass', 'learningArea'])->find($this->selectedExam) : null,
            'reviewQueue' => $reviewQueue,
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
