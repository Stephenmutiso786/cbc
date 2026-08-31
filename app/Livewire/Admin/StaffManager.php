<?php

namespace App\Livewire\Admin;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class StaffManager extends Component
{
    use WithFileUploads;

    public bool $showForm = false;
    public bool $showImport = false;
    public ?int $editingId = null;
    public $csvFile;
    public array $form = ['staff_number' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'phone_number' => '', 'gender' => 'male', 'employment_type' => 'permanent', 'staff_type' => 'teaching', 'designation' => '', 'date_joined' => '', 'role' => 'teacher', 'password' => ''];
    public array $importErrors = [];
    public int $importedCount = 0;

    public function create(): void
    {
        $this->editingId = null;
        $this->form = ['staff_number' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'phone_number' => '', 'gender' => 'male', 'employment_type' => 'permanent', 'staff_type' => 'teaching', 'designation' => '', 'date_joined' => now()->format('Y-m-d'), 'role' => 'teacher', 'password' => ''];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $staff = StaffMember::with('user')->findOrFail($id);
        $this->editingId = $id;
        $this->form = array_merge($staff->only(['staff_number', 'first_name', 'last_name', 'email', 'phone_number', 'gender', 'employment_type', 'staff_type', 'designation', 'date_joined']), ['role' => $staff->user?->getRoleNames()->first() ?: 'teacher', 'password' => '']);
        $this->form['date_joined'] = $staff->date_joined?->format('Y-m-d');
        $this->showForm = true;
    }

    public function save(): void
    {
        $staff = $this->editingId ? StaffMember::findOrFail($this->editingId) : null;
        $data = $this->validate([
            'form.staff_number' => ['required', 'string', 'max:255', Rule::unique('staff_members', 'staff_number')->ignore($this->editingId)],
            'form.first_name' => ['required', 'string', 'max:255'], 'form.last_name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', Rule::unique('staff_members', 'email')->ignore($this->editingId)],
            'form.phone_number' => ['required', 'string', 'max:50'], 'form.gender' => ['required', 'in:male,female'],
            'form.employment_type' => ['required', 'in:permanent,contract,bom,volunteer'], 'form.staff_type' => ['required', 'in:teaching,non_teaching'],
            'form.designation' => ['nullable', 'string', 'max:255'], 'form.date_joined' => ['required', 'date'],
            'form.role' => ['required', 'exists:roles,name'], 'form.password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
        ])['form'];
        DB::transaction(function () use ($data, $staff) {
            $user = $staff?->user;
            if (!$user) $user = User::create(['name' => $data['first_name'] . ' ' . $data['last_name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'email_verified_at' => now()]);
            else {
                $user->update(['name' => $data['first_name'] . ' ' . $data['last_name'], 'email' => $data['email']]);
                if ($data['password']) $user->update(['password' => Hash::make($data['password'])]);
            }
            $user->syncRoles([$data['role']]);
            $staff = $staff ?: new StaffMember();
            $staff->fill(array_diff_key($data, array_flip(['role', 'password'])))->forceFill(['user_id' => $user->id, 'is_active' => true])->save();
        });
        $this->showForm = false;
        session()->flash('success', $this->editingId ? 'Staff record updated.' : 'Staff account created.');
    }

    public function importCsv(): void
    {
        $this->validate(['csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $handle = fopen($this->csvFile->getRealPath(), 'r');
        $headers = array_map(fn ($v) => strtolower(trim((string) $v)), fgetcsv($handle) ?: []);
        $this->importErrors = []; $this->importedCount = 0; $rowNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $row = [];
            foreach ($headers as $i => $header) $row[$header] = trim((string) ($values[$i] ?? ''));
            $row['role'] = $row['role'] ?: 'teacher';
            $row['password'] = $row['password'] ?: 'ChangeMe@123';
            $validator = validator($row, ['staff_number' => 'required|unique:staff_members,staff_number', 'first_name' => 'required|string|max:255', 'last_name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'phone_number' => 'required|string|max:50', 'gender' => 'required|in:male,female', 'employment_type' => 'required|in:permanent,contract,bom,volunteer', 'staff_type' => 'required|in:teaching,non_teaching', 'date_joined' => 'required|date', 'role' => 'required|exists:roles,name', 'password' => 'required|min:8']);
            if ($validator->fails()) { $this->importErrors[] = 'Row ' . $rowNumber . ': ' . implode(' ', $validator->errors()->all()); continue; }
            DB::transaction(function () use ($row) {
                $user = User::create(['name' => $row['first_name'] . ' ' . $row['last_name'], 'email' => $row['email'], 'password' => Hash::make($row['password']), 'email_verified_at' => now()]);
                $user->assignRole($row['role']);
                StaffMember::create(array_merge($row, ['user_id' => $user->id, 'is_active' => true]));
            });
            $this->importedCount++;
        }
        fclose($handle);
        if ($this->importedCount) session()->flash('success', "{$this->importedCount} staff account(s) imported.");
        if (!$this->importErrors) $this->showImport = false;
    }

    public function render()
    {
        return view('livewire.admin.staff-manager', ['staff' => StaffMember::with('user')->orderBy('last_name')->paginate(25), 'roles' => \Spatie\Permission\Models\Role::orderBy('name')->get()])->layout('layouts.admin');
    }
}
