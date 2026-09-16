<?php

namespace App\Livewire\SuperAdmin;

use App\Models\School;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class SchoolManager extends Component
{
    use WithPagination;
    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = ['name' => '', 'type' => 'primary', 'motto' => '', 'address' => '', 'phone' => '', 'email' => '', 'is_active' => true];
    public string $adminName = '';
    public string $adminEmail = '';
    public ?string $generatedPassword = null;

    public function mount(): void { abort_unless(auth()->user()?->hasRole('super-admin'), 403); }
    public function create(): void { $this->resetValidation(); $this->editingId = null; $this->generatedPassword = null; $this->form = ['name' => '', 'type' => 'primary', 'motto' => '', 'address' => '', 'phone' => '', 'email' => '', 'is_active' => true]; $this->adminName = $this->adminEmail = ''; $this->showForm = true; }
    public function edit(int $id): void { $school = School::findOrFail($id); $this->editingId = $id; $this->form = $school->only(['name','type','motto','address','phone','email','is_active']); $this->showForm = true; }
    public function save(): void
    {
        $rules = ['form.name' => ['required','string','max:255'], 'form.type' => ['required','in:primary,secondary,mixed'], 'form.motto' => ['nullable','string','max:255'], 'form.address' => ['nullable','string','max:500'], 'form.phone' => ['nullable','string','max:50'], 'form.email' => ['nullable','email','max:255'], 'form.is_active' => ['boolean']];
        if (!$this->editingId) { $rules['adminName'] = ['required','string','max:255']; $rules['adminEmail'] = ['required','email','max:255',Rule::unique('users','email')]; }
        $this->validate($rules);
        if ($this->editingId) { School::findOrFail($this->editingId)->update($this->form); $this->showForm = false; session()->flash('success', 'School updated.'); return; }
        $slug = Str::slug($this->form['name']) ?: 'school'; while (School::where('slug', $slug)->exists()) $slug = Str::slug($this->form['name']) . '-' . Str::lower(Str::random(4));
        $school = School::create([...$this->form, 'slug' => $slug]); $password = Str::password(12);
        Tenant::run($school->id, fn () => tap(User::create(['name' => $this->adminName, 'email' => $this->adminEmail, 'password' => Hash::make($password), 'email_verified_at' => now()]), fn (User $user) => $user->assignRole('school-admin')));
        $this->generatedPassword = $password; $this->showForm = false; session()->flash('success', "School {$school->name} created. The temporary admin password is shown below once.");
    }
    public function toggleActive(int $id): void { $school = School::findOrFail($id); $school->update(['is_active' => !$school->is_active]); }
    public function render() { return view('livewire.super-admin.school-manager', ['schools' => School::withCount(['users','learners'])->orderBy('name')->paginate(15)])->layout('layouts.admin'); }
}
