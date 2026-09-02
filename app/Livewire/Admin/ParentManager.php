<?php

namespace App\Livewire\Admin;

use App\Models\Guardian;
use App\Models\Learner;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ParentManager extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $firstName = '';
    public string $lastName = '';
    public string $phoneNumber = '';
    public string $email = '';
    public string $relationship = 'Parent';
    public array $learnerIds = [];

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $parent = Guardian::with('learners')->findOrFail($id);
        $this->editingId = $id;
        $this->firstName = $parent->first_name;
        $this->lastName = $parent->last_name;
        $this->phoneNumber = $parent->phone_number;
        $this->email = (string) $parent->email;
        $this->relationship = (string) ($parent->relationship ?: 'Parent');
        $this->learnerIds = $parent->learners->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'phoneNumber' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'relationship' => ['required', 'string', 'max:50'],
            'learnerIds' => ['required', 'array', 'min:1'],
            'learnerIds.*' => ['integer', 'exists:learners,id'],
        ]);

        $duplicate = Guardian::where('phone_number', $data['phoneNumber'])
            ->when($this->editingId, fn ($query) => $query->where('id', '!=', $this->editingId))
            ->exists();
        abort_if($duplicate, 422, 'A parent with this phone number already exists.');

        DB::transaction(function () use ($data): void {
            $parent = $this->editingId ? Guardian::findOrFail($this->editingId) : new Guardian();
            $parent->fill([
                'first_name' => $data['firstName'], 'last_name' => $data['lastName'],
                'phone_number' => $data['phoneNumber'], 'email' => $data['email'] ?: null,
                'relationship' => $data['relationship'], 'is_primary_contact' => true,
            ])->save();
            $parent->learners()->sync($data['learnerIds']);
        });

        $this->showForm = false;
        session()->flash('success', $this->editingId ? 'Parent record updated and learner links saved.' : 'Parent record created and learner links saved.');
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Guardian::findOrFail($id)->delete();
        session()->flash('success', 'Parent record deleted. Learners were not deleted.');
    }

    public function render()
    {
        return view('livewire.admin.parent-manager', [
            'parents' => Guardian::withCount('learners')->with('learners.schoolClass')->orderBy('last_name')->paginate(25),
            'learners' => Learner::active()->with('schoolClass')->orderBy('last_name')->orderBy('first_name')->get(),
        ])->layout('layouts.admin');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'firstName', 'lastName', 'phoneNumber', 'email', 'learnerIds']);
        $this->relationship = 'Parent';
    }
}
