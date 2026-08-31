<?php

namespace App\Livewire\Students;

use App\Models\Learner;
use App\Models\SchoolClass;
use Livewire\Component;
use Livewire\WithPagination;

class StudentList extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $gradeFilter  = '';
    public string $classFilter  = '';
    public string $statusFilter = '1';
    public string $boardingFilter = '';
    public int    $perPage      = 25;

    public bool $showForm = false;
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
