<?php

namespace App\Livewire\Admin;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AcademicPeriods extends Component
{
    public ?int $editingId = null;
    public int $year;
    public string $startsOn = '';
    public string $endsOn = '';
    public array $terms = [];
    public string $notice = '';

    public function mount(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->year = (int) config('school.academic_year');
        $this->startsOn = '';
        $this->endsOn = '';
        $this->terms = [
            1 => ['name' => 'Term 1', 'starts_on' => '', 'ends_on' => ''],
            2 => ['name' => 'Term 2', 'starts_on' => '', 'ends_on' => ''],
            3 => ['name' => 'Term 3', 'starts_on' => '', 'ends_on' => ''],
        ];
    }

    public function edit(int $id): void
    {
        $academicYear = AcademicYear::with('terms')->findOrFail($id);
        $this->editingId = $id;
        $this->year = $academicYear->year;
        $this->startsOn = $academicYear->starts_on?->format('Y-m-d') ?? '';
        $this->endsOn = $academicYear->ends_on?->format('Y-m-d') ?? '';
        foreach ($academicYear->terms as $term) {
            $this->terms[$term->number] = [
                'name' => $term->name,
                'starts_on' => $term->starts_on?->format('Y-m-d') ?? '',
                'ends_on' => $term->ends_on?->format('Y-m-d') ?? '',
            ];
        }
    }

    public function save(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2200', 'unique:academic_years,year,' . ($this->editingId ?? 'NULL')],
            'startsOn' => ['nullable', 'date'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'terms.*.name' => ['required', 'string', 'max:100'],
            'terms.*.starts_on' => ['nullable', 'date'],
            'terms.*.ends_on' => ['nullable', 'date'],
        ]);

        DB::transaction(function (): void {
            $academicYear = AcademicYear::updateOrCreate(
                ['id' => $this->editingId],
                ['year' => $this->year, 'starts_on' => $this->startsOn ?: null, 'ends_on' => $this->endsOn ?: null]
            );
            foreach ($this->terms as $number => $term) {
                AcademicTerm::updateOrCreate(
                    ['academic_year_id' => $academicYear->id, 'number' => $number],
                    ['name' => $term['name'], 'starts_on' => $term['starts_on'] ?: null, 'ends_on' => $term['ends_on'] ?: null]
                );
            }
            $this->editingId = $academicYear->id;
        });

        $this->notice = 'Academic year and terms saved.';
    }

    public function activateYear(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $academicYear = AcademicYear::with('terms')->findOrFail($id);
        DB::transaction(function () use ($academicYear): void {
            AcademicYear::query()->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
            AcademicTerm::where('academic_year_id', $academicYear->id)->update(['is_active' => false]);
            $term = $academicYear->terms->firstWhere('number', 1) ?? $academicYear->terms->first();
            if ($term) {
                $term->update(['is_active' => true]);
                $this->saveCurrentSettings($academicYear->year, $term->number);
            }
        });
        $this->notice = "Academic year {$academicYear->year} is now active.";
    }

    public function activateTerm(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $term = AcademicTerm::with('academicYear')->findOrFail($id);
        DB::transaction(function () use ($term): void {
            AcademicYear::query()->update(['is_active' => false]);
            AcademicYear::whereKey($term->academic_year_id)->update(['is_active' => true]);
            AcademicTerm::where('academic_year_id', $term->academic_year_id)->update(['is_active' => false]);
            $term->update(['is_active' => true]);
            $this->saveCurrentSettings($term->academicYear->year, $term->number);
        });
        $this->notice = "{$term->name}, {$term->academicYear->year} is now active.";
    }

    public function render()
    {
        return view('livewire.admin.academic-periods', [
            'academicYears' => AcademicYear::with('terms')->orderByDesc('year')->get(),
        ])->layout('layouts.admin');
    }

    private function saveCurrentSettings(int $year, int $term): void
    {
        SchoolSetting::updateOrCreate(['key' => 'academic_year'], ['value' => (string) $year]);
        SchoolSetting::updateOrCreate(['key' => 'current_term'], ['value' => (string) $term]);
        config()->set('school.academic_year', $year);
        config()->set('school.current_academic_year', $year);
        config()->set('school.current_term', $term);
    }

    private function canManage(): bool
    {
        return auth()->user()->can('manage system settings');
    }
}
