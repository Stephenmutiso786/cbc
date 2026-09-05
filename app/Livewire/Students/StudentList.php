<?php

namespace App\Livewire\Students;

use App\Models\Learner;
use App\Models\SchoolClass;
use App\Jobs\GenerateReportCardJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Http\Request;

class StudentList extends Component
{
    use WithPagination, WithFileUploads;

    public string $search       = '';
    public string $gradeFilter  = '';
    public string $classFilter  = '';
    public string $statusFilter = '1';
    public string $boardingFilter = '';
    public int    $perPage      = 25;

    public bool $showForm = false;
    public bool $showImport = false;
    public $csvFile;
    public string $pasteNames = '';
    public string $importGrade = '';
    public string $importClassId = '';
    public array $importErrors = [];
    public array $selectedIds = [];
    public int $importedCount = 0;
    public ?int $editingId = null;
    public array $form = [
        'admission_number' => '', 'first_name' => '', 'middle_name' => '', 'last_name' => '',
        'date_of_birth' => '', 'grade_level' => '', 'class_id' => '',
        'admission_date' => '', 'boarding_status' => 'day', 'academic_year' => '',
    ];

    protected $queryString = ['search', 'gradeFilter', 'classFilter', 'statusFilter', 'boardingFilter'];

    public function mount(Request $request): void
    {
        $this->showImport = $request->boolean('import') || $request->routeIs('admin.students.import');
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingGradeFilter(): void { $this->resetPage(); }
    public function updatingClassFilter(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingBoardingFilter(): void { $this->resetPage(); }

    public function updatedSelectedIds(): void
    {
        $this->selectedIds = array_values(array_unique(array_map('intval', $this->selectedIds)));
    }

    public function selectAllMatching(): void
    {
        abort_unless($this->canDelete(), 403);
        $this->selectedIds = $this->filteredQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function bulkDelete(): void
    {
        abort_unless($this->canDelete(), 403);
        $ids = array_values(array_filter(array_map('intval', $this->selectedIds)));
        abort_if($ids === [], 422, 'Select at least one learner.');
        DB::transaction(fn () => Learner::whereIn('id', $ids)->get()->each->delete());
        $this->selectedIds = [];
        $this->resetPage();
        session()->flash('success', count($ids) . ' learner(s) deleted.');
    }

    public function create(): void
    {
        abort_unless(auth()->user()->can('create students'), 403);
        $this->resetValidation();
        $this->editingId = null;
        $this->form = array_merge($this->form, [
            'admission_number' => '', 'first_name' => '', 'middle_name' => '', 'last_name' => '',
            'date_of_birth' => '', 'grade_level' => '', 'class_id' => '',
            'admission_date' => now()->format('Y-m-d'), 'boarding_status' => 'day',
            'academic_year' => (string) config('school.academic_year'),
        ]);
        $this->showForm = true;
    }

    public function openImport(): void
    {
        abort_unless(auth()->user()->can('create students'), 403);
        $this->reset(['csvFile', 'pasteNames', 'importErrors', 'importedCount']);
        $this->importGrade = '';
        $this->importClassId = '';
        $this->showImport = true;
    }

    public function updatedImportClassId($classId): void
    {
        if ($classId && !$this->importGrade) {
            $this->importGrade = (string) SchoolClass::find($classId)?->grade_level;
        }
    }

    public function updatedFormClassId($classId): void
    {
        if ($classId) {
            $this->form['grade_level'] = (string) SchoolClass::forConfiguredGrades()->find($classId)?->grade_level;
        }
    }

    public function importLearners(): void
    {
        abort_unless(auth()->user()->can('create students'), 403);
        $this->validate([
            'csvFile' => ['nullable', 'file', 'mimes:csv,txt', 'max:1900'],
            'importGrade' => ['nullable', 'string'],
            'importClassId' => ['required', 'integer', 'exists:school_classes,id'],
        ]);

        if (!$this->csvFile && trim($this->pasteNames) === '') {
            $this->addError('pasteNames', 'Paste learner names or choose a CSV file.');
            return;
        }

        $rows = $this->csvFile ? $this->readCsvRows() : $this->readPastedRows();
        $this->importErrors = [];
        $this->importedCount = 0;

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $row['class_id'] = $row['class_id'] ?: $this->importClassId;
            $class = SchoolClass::forConfiguredGrades()->find($row['class_id']);
            $row['grade_level'] = (string) $class?->grade_level;
            if ($this->importGrade && $class && $this->importGrade !== $row['grade_level']) {
                $this->importErrors[] = 'Row ' . $line . ': selected grade does not match the selected class.';
                continue;
            }
            $row['admission_date'] = $row['admission_date'] ?: now()->format('Y-m-d');
            $row['date_of_birth'] = $row['date_of_birth'] ?: null;
            $row['academic_year'] = $row['academic_year'] ?: (string) config('school.academic_year');
            $row['boarding_status'] = strtolower($row['boarding_status'] ?: 'day');
            $row['admission_number'] = $row['admission_number'] ?: $this->newAdmissionNumber((int) $row['class_id']);

            if ($this->duplicateNameExists($row['first_name'], $row['middle_name'], $row['last_name'], (int) $row['class_id'], $row['academic_year'])) {
                $this->importErrors[] = 'Row ' . $line . ': a learner with the same name already exists in this class and academic year.';
                continue;
            }

            $validator = Validator::make($row, [
                'admission_number' => ['required', 'string', 'max:255', 'unique:learners,admission_number'],
                'first_name' => ['required', 'string', 'max:255'],
                'middle_name' => ['nullable', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'date_of_birth' => ['nullable', 'date'],
                'grade_level' => ['required', 'string', 'max:255'],
                'class_id' => ['required', 'integer', 'exists:school_classes,id'],
                'admission_date' => ['required', 'date'],
                'boarding_status' => ['required', 'in:day,boarding'],
                'academic_year' => ['required', 'string', 'max:9'],
            ]);

            if ($validator->fails()) {
                $this->importErrors[] = 'Row ' . $line . ': ' . implode(' ', $validator->errors()->all());
                continue;
            }

            try {
                DB::transaction(fn () => Learner::create([
                    'admission_number' => $row['admission_number'],
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'] ?: null,
                    'last_name' => $row['last_name'],
                    'date_of_birth' => $row['date_of_birth'] ?: null,
                    'gender' => 'male',
                    'grade_level' => $row['grade_level'],
                    'class_id' => $row['class_id'],
                    'admission_date' => $row['admission_date'],
                    'boarding_status' => $row['boarding_status'],
                    'academic_year' => $row['academic_year'],
                    'is_active' => true,
                ]));
                $this->importedCount++;
            } catch (\Throwable $e) {
                $this->importErrors[] = 'Row ' . $line . ': could not be saved (' . $e->getMessage() . ').';
            }
        }

        if ($this->importedCount) {
            $this->resetPage();
            session()->flash('success', "{$this->importedCount} learner(s) imported successfully.");
        }
        if (!$this->importErrors) {
            $this->showImport = false;
        }
    }

    private function readPastedRows(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', trim($this->pasteNames)))
            ->filter(fn ($line) => trim($line) !== '')
            ->map(function ($line) {
                $parts = preg_split('/[\t,]+|\\s+/', trim($line));
                return [
                    'first_name' => $parts[0] ?? '',
                    'middle_name' => count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : '',
                    'last_name' => count($parts) > 1 ? end($parts) : '',
                    'admission_number' => '',
                    'date_of_birth' => '', 'grade_level' => '',
                    'class_id' => '', 'admission_date' => '', 'boarding_status' => '', 'academic_year' => '',
                ];
            })->values()->all();
    }

    private function readCsvRows(): array
    {
        $handle = fopen($this->csvFile->getRealPath(), 'r');
        $headers = array_map(fn ($header) => preg_replace('/^\xEF\xBB\xBF/', '', strtolower(trim((string) $header))), fgetcsv($handle) ?: []);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            $row = array_fill_keys(['admission_number','first_name','middle_name','last_name','date_of_birth','grade_level','class_id','admission_date','boarding_status','academic_year'], '');
            foreach ($headers as $position => $header) {
                if (array_key_exists($header, $row)) $row[$header] = trim((string) ($values[$position] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function newAdmissionNumber(int $classId): string
    {
        $class = SchoolClass::findOrFail($classId);
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', $class->name));
        $prefix = trim($prefix, '-') ?: 'CLASS';
        $next = Learner::withTrashed()->where('class_id', $classId)->count() + 1;
        do {
            $number = $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (Learner::withTrashed()->where('admission_number', $number)->exists());
        return $number;
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->can('edit students'), 403);
        $this->resetValidation();
        $learner = Learner::findOrFail($id);
        $this->editingId = $id;
        $this->form = [
            'admission_number' => $learner->admission_number, 'first_name' => $learner->first_name,
            'middle_name' => $learner->middle_name ?? '', 'last_name' => $learner->last_name,
            'date_of_birth' => $learner->date_of_birth?->format('Y-m-d'),
            'grade_level' => $learner->grade_level->value, 'class_id' => (string) $learner->class_id,
            'admission_date' => $learner->admission_date?->format('Y-m-d'),
            'boarding_status' => $learner->boarding_status, 'academic_year' => (string) $learner->academic_year,
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can($this->editingId ? 'edit students' : 'create students'), 403);
        $data = $this->validate([
            'form.admission_number' => [$this->editingId ? 'required' : 'nullable', 'string', 'max:255', Rule::unique('learners', 'admission_number')->ignore($this->editingId)],
            'form.first_name' => 'required|string|max:255', 'form.middle_name' => 'nullable|string|max:255',
            'form.last_name' => 'required|string|max:255', 'form.date_of_birth' => 'nullable|date',
            'form.grade_level' => 'required|string',
            'form.class_id' => 'required|exists:school_classes,id', 'form.admission_date' => 'required|date',
            'form.boarding_status' => 'required|in:day,boarding', 'form.academic_year' => 'required|string|max:9',
        ])['form'];
        $data['grade_level'] = (string) SchoolClass::forConfiguredGrades()->findOrFail($data['class_id'])->grade_level;
        if (! $this->editingId) {
            $data['admission_number'] = $this->newAdmissionNumber((int) $data['class_id']);
        }
        if ($this->duplicateNameExists($data['first_name'], $data['middle_name'], $data['last_name'], (int) $data['class_id'], $data['academic_year'], $this->editingId)) {
            $this->addError('form.first_name', 'A learner with the same name already exists in this class and academic year.');
            return;
        }
        $learner = $this->editingId ? Learner::findOrFail($this->editingId) : new Learner();
        $learner->fill($data);
        if (! $learner->gender) $learner->gender = 'male';
        $learner->is_active = true;
        $learner->save();
        $this->showForm = false;
        session()->flash('success', $this->editingId ? 'Learner updated successfully.' : 'Learner enrolled successfully.');
    }

    public function view(int $id): void
    {
        $this->edit($id);
    }

    public function generateReport(int $id): void
    {
        abort_unless(auth()->user()->can('generate report cards'), 403);
        Learner::findOrFail($id);
        GenerateReportCardJob::dispatch($id, (string) config('school.current_term'), (string) config('school.academic_year'));
        session()->flash('success', 'The report card was queued for generation. It will be saved to the configured report storage.');
    }

    public function render()
    {
        $learners = $this->filteredQuery()->with(['schoolClass'])
            ->when($this->search, fn($q) => $q->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('admission_number', 'like', "%{$this->search}%")
                  ->orWhere('kemis_upi', 'like', "%{$this->search}%");
            }))
            ->when($this->gradeFilter, fn($q) => $q->where('grade_level', $this->gradeFilter))
            ->when($this->classFilter, fn($q) => $q->where('class_id', $this->classFilter))
            ->when($this->statusFilter === '1', fn($q) => $q->where('is_active', true))
            ->when($this->statusFilter === '0', fn($q) => $q->where('is_active', false))
            ->when($this->boardingFilter, fn($q) => $q->where('boarding_status', $this->boardingFilter))
            ->orderBy('class_id')
            ->orderBy('last_name')
            ->paginate($this->perPage);

        return view('livewire.students.student-list', [
            'learners' => $learners,
            'classes'  => SchoolClass::forConfiguredGrades()->withCount(['learners as active_learners_count' => fn ($query) => $query->where('is_active', true)])->orderBy('grade_level')->get(),
        ])->layout('layouts.admin');
    }

    private function filteredQuery()
    {
        return Learner::query()
            ->when($this->search, fn($q) => $q->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('admission_number', 'like', "%{$this->search}%")
                  ->orWhere('kemis_upi', 'like', "%{$this->search}%");
            }))
            ->when($this->gradeFilter, fn($q) => $q->where('grade_level', $this->gradeFilter))
            ->when($this->classFilter, fn($q) => $q->where('class_id', $this->classFilter))
            ->when($this->statusFilter === '1', fn($q) => $q->where('is_active', true))
            ->when($this->statusFilter === '0', fn($q) => $q->where('is_active', false))
            ->when($this->boardingFilter, fn($q) => $q->where('boarding_status', $this->boardingFilter));
    }

    private function duplicateNameExists(string $first, ?string $middle, string $last, int $classId, string $year, ?int $ignoreId = null): bool
    {
        $key = $this->nameKey($first, $middle, $last);
        return Learner::where('class_id', $classId)->where('academic_year', $year)
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->get(['id', 'first_name', 'middle_name', 'last_name'])
            ->contains(fn ($learner) => $this->nameKey($learner->first_name, $learner->middle_name, $learner->last_name) === $key);
    }

    private function nameKey(string $first, ?string $middle, string $last): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', trim($first . ' ' . ($middle ?? '') . ' ' . $last))));
    }

    private function canDelete(): bool
    {
        return auth()->user()->can('delete students');
    }
}
