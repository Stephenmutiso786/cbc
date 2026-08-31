<?php

namespace App\Livewire\Admin;

use App\Models\LearningArea;
use Livewire\Component;

class SubjectManager extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = ['name' => '', 'code' => '', 'grade_level' => '', 'weekly_lessons' => 5];

    public function create(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'code' => '', 'grade_level' => '', 'weekly_lessons' => 5];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->editingId = $id;
        $this->form = LearningArea::findOrFail($id)->only(['name', 'code', 'grade_level', 'weekly_lessons']);
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:50', 'unique:learning_areas,code,' . ($this->editingId ?: 'NULL')],
            'form.grade_level' => ['required', 'string', 'max:255'],
            'form.weekly_lessons' => ['required', 'integer', 'min:1', 'max:50'],
        ])['form'];
        $area = $this->editingId ? LearningArea::findOrFail($this->editingId) : new LearningArea();
        $area->fill($data)->save();
        $this->showForm = false;
        session()->flash('success', 'Learning area saved successfully.');
    }

    public function toggle(int $id): void
    {
        $area = LearningArea::findOrFail($id);
        $area->update(['is_active' => !$area->is_active]);
    }

    public function render()
    {
        return view('livewire.admin.subject-manager', ['subjects' => LearningArea::orderBy('name')->get(), 'grades' => array_merge(...array_values(config('school.grade_levels')))])
            ->layout('layouts.admin');
    }
}
