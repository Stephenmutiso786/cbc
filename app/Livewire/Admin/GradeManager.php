<?php

namespace App\Livewire\Admin;

use App\Models\GradingScale;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GradeManager extends Component
{
    public ?int $editingId = null;
    public string $name = '';
    public string $description = '';
    public string $type = 'letter';
    public string $bandsText = "A|80|100|Excellent\nB|70|79.99|Very good\nC|60|69.99|Good\nD|50|59.99|Pass\nE|0|49.99|Needs improvement";
    public array $classIds = [];
    public string $saved = '';

    public function save(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:letter,rubric',
            'bandsText' => 'required|string|max:5000',
            'classIds' => 'array',
        ]);

        $bands = $this->parseBands();
        if ($bands === []) {
            $this->addError('bandsText', 'Add bands using CODE|MIN|MAX|LABEL, one per line.');
            return;
        }

        DB::transaction(function () use ($bands): void {
            $scale = GradingScale::updateOrCreate(
                ['id' => $this->editingId],
                ['name' => $this->name, 'description' => $this->description ?: null, 'type' => $this->type, 'bands' => $bands, 'is_active' => true]
            );
            $this->syncClasses($scale);
            $this->editingId = $scale->id;
        });

        $this->saved = 'Grade scale saved and allocated to ' . count($this->classIds) . ' class(es).';
        $this->resetForm(false);
    }

    public function edit(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $scale = GradingScale::with('classes')->findOrFail($id);
        $this->editingId = $scale->id;
        $this->name = $scale->name;
        $this->description = $scale->description ?? '';
        $this->type = $scale->type;
        $this->bandsText = collect($scale->bands ?? [])->map(fn ($band) => implode('|', [$band['code'] ?? '', $band['min'] ?? 0, $band['max'] ?? 100, $band['label'] ?? '']))->implode("\n");
        $this->classIds = $scale->classes->filter(fn ($class) => (string) $class->pivot->academic_year === (string) config('school.academic_year'))->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function delete(int $id): void
    {
        abort_unless($this->canManage(), 403);
        GradingScale::findOrFail($id)->delete();
        if ($this->editingId === $id) $this->resetForm();
        $this->saved = 'Grade scale deleted.';
    }

    public function resetForm(bool $clearMessage = true): void
    {
        $this->reset(['editingId', 'name', 'description', 'classIds']);
        $this->type = 'letter';
        $this->bandsText = "A|80|100|Excellent\nB|70|79.99|Very good\nC|60|69.99|Good\nD|50|59.99|Pass\nE|0|49.99|Needs improvement";
        if ($clearMessage) $this->saved = '';
    }

    public function render()
    {
        return view('livewire.admin.grade-manager', [
            'scales' => GradingScale::with(['classes' => fn ($query) => $query->wherePivot('academic_year', (string) config('school.academic_year'))])->latest()->get(),
            'classes' => SchoolClass::forConfiguredGrades()->where('is_active', true)->orderBy('grade_level')->orderBy('name')->get(),
        ])->layout('layouts.admin');
    }

    private function parseBands(): array
    {
        $bands = [];
        foreach (preg_split('/\r?\n/', $this->bandsText) as $line) {
            $parts = array_map('trim', explode('|', $line, 4));
            if (count($parts) < 4 || $parts[0] === '' || ! is_numeric($parts[1]) || ! is_numeric($parts[2]) || (float) $parts[1] > (float) $parts[2]) continue;
            $bands[] = ['code' => $parts[0], 'min' => (float) $parts[1], 'max' => (float) $parts[2], 'label' => $parts[3]];
        }
        return $bands;
    }

    private function syncClasses(GradingScale $scale): void
    {
        $scale->classes()->wherePivot('academic_year', (string) config('school.academic_year'))->detach();
        if ($this->classIds) {
            $scale->classes()->attach(array_fill_keys($this->classIds, ['academic_year' => (string) config('school.academic_year')]));
        }
    }

    private function canManage(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal']);
    }
}
