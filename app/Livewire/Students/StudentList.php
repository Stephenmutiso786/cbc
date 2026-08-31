<?php

namespace App\Livewire\Students;

use App\Models\Learner;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

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
    public int $importedCount = 0;
    public ?int $editingId = null;
    public array $form = [
        'admission_number' => '', 'first_name' => '', 'middle_name' => '', 'last_name' => '',
        'date_of_birth' => '', 'gender' => 'male', 'grade_level' => '', 'class_id' => '',
        'admission_date' => '', 'boarding_status' => 'day', 'academic_year' => '',
    ];

    protected $queryString = ['search', 'gradeFilter', 'classFilter', 'statusFilter', 'boardingFilter'];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingGradeFilter(): void { $this->resetPage(); }
    public function updatingClassFilter(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingBoardingFilter(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = array_merge($this->form, [
            'admission_number' => '', 'first_name' => '', 'middle_name' => '', 'last_name' => '',
            'date_of_birth' => '', 'gender' => 'male', 'grade_level' => '', 'class_id' => '',
            'admission_date' => now()->format('Y-m-d'), 'boarding_status' => 'day',
            'academic_year' => (string) config('school.academic_year'),
        ]);
        $this->showForm = true;
    }

    public function openImport(): void
    {
        $this->reset(['csvFile', 'pasteNames', 'importErrors', 'importedCount']);
        $this->importGrade = '';
        $this->importClassId = '';
        $this->showImport = true;
    }

    public function importLearners(): void
    {
        $this->validate([
            'csvFile' => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'importGrade' => ['required_without:csvFile', 'nullable', 'string'],
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
            $row['grade_level'] = $row['grade_level'] ?: $this->importGrade;
            $row['admission_date'] = $row['admission_date'] ?: now()->format('Y-m-d');
            $row['date_of_birth'] = $row['date_of_birth'] ?: now()->subYears(6)->format('Y-m-d');
            $row['academic_year'] = $row['academic_year'] ?: (string) config('school.academic_year');
            $row['gender'] = strtolower($row['gender'] ?: 'male');
            $row['boarding_status'] = strtolower($row['boarding_status'] ?: 'day');
            $row['admission_number'] = $row['admission_number'] ?: $this->newAdmissionNumber();

            $validator = Validator::make($row, [
                'admission_number' => ['required', 'string', 'max:255', 'unique:learners,admission_number'],
                'first_name' => ['required', 'string', 'max:255'],
                'middle_name' => ['nullable', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'date_of_birth' => ['required', 'date'],
                'gender' => ['required', 'in:male,female'],
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
                    'date_of_birth' => $row['date_of_birth'],
                    'gender' => $row['gender'],
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
                    'date_of_birth' => '', 'gender' => '', 'grade_level' => '',
                    'class_id' => '', 'admission_date' => '', 'boarding_status' => '', 'academic_year' => '',
                ];
            })->values()->all();
    }

    private function readCsvRows(): array
    {
        $handle = fopen($this->csvFile->getRealPath(), 'r');
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), fgetcsv($handle) ?: []);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            $row = array_fill_keys(['admission_number','first_name','middle_name','last_name','date_of_birth','gender','grade_level','class_id','admission_date','boarding_status','academic_year'], '');
            foreach ($headers as $position => $header) {
                if (array_key_exists($header, $row)) $row[$header] = trim((string) ($values[$position] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function newAdmissionNumber(): string
    {
        do {
            $number = 'IMP-' . now()->format('Ymd') . '-' . random_int(1000, 9999);
        } while (Learner::withTrashed()->where('admission_number', $number)->exists());
        return $number;
    }

    public function edit(int $id): void
    {
        $learner = Learner::findOrFail($id);
        $this->editingId = $id;
        $this->form = [
            'admission_number' => $learner->admission_number, 'first_name' => $learner->first_name,
            'middle_name' => $learner->middle_name ?? '', 'last_name' => $learner->last_name,
            'date_of_birth' => $learner->date_of_birth?->format('Y-m-d'), 'gender' => $learner->gender,
            'grade_level' => $learner->grade_level->value, 'class_id' => (string) $learner->class_id,
            'admission_date' => $learner->admission_date?->format('Y-m-d'),
            'boarding_status' => $learner->boarding_status, 'academic_year' => (string) $learner->academic_year,
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.admission_number' => 'required|string|max:255|unique:learners,admission_number,' . ($this->editingId ?? 'NULL'),
            'form.first_name' => 'required|string|max:255', 'form.middle_name' => 'nullable|string|max:255',
            'form.last_name' => 'required|string|max:255', 'form.date_of_birth' => 'required|date',
            'form.gender' => 'required|in:male,female', 'form.grade_level' => 'required|string',
            'form.class_id' => 'required|exists:school_classes,id', 'form.admission_date' => 'required|date',
            'form.boarding_status' => 'required|in:day,boarding', 'form.academic_year' => 'required|string|max:9',
        ])['form'];
        $learner = $this->editingId ? Learner::findOrFail($this->editingId) : new Learner();
        $learner->fill($data);
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
        session()->flash('success', 'Select this learner in the reports section to generate their report card.');
    }

    public function render()
    {
        $learners = Learner::with(['schoolClass'])
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
            ->orderBy('last_name')
            ->paginate($this->perPage);

        return view('livewire.students.student-list', [
            'learners' => $learners,
            'classes'  => SchoolClass::orderBy('grade_level')->get(),
        ])->layout('layouts.admin');
    }
}
