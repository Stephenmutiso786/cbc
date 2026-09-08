<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserAccountManager extends Component
{
    use WithPagination;

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '', $email = '', $password = '', $role = 'teacher', $notice = '';

    public function mount(): void { abort_unless(auth()->user()->can('manage users'), 403); }
    public function create(): void { $this->reset(['editingId', 'name', 'email', 'password', 'notice']); $this->role = 'teacher'; $this->showForm = true; }

    public function edit(int $id): void
    {
        $user = User::with('roles')->findOrFail($id);
        $this->guardTarget($user);
        $this->editingId = $id; $this->name = $user->name; $this->email = $user->email; $this->role = $user->roles->first()?->name ?: 'teacher'; $this->password = ''; $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);
        $data = $this->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)], 'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'], 'role' => ['required', Rule::in($this->assignableRoles())]]);
        $user = $this->editingId ? User::findOrFail($this->editingId) : new User();
        $this->guardTarget($user, false);
        $user->name = $data['name']; $user->email = $data['email'];
        if ($data['password']) $user->password = Hash::make($data['password']);
        $user->email_verified_at ??= now(); $user->save(); $user->syncRoles([$data['role']]);
        $this->notice = $this->editingId ? 'User account updated.' : 'User account created with its password.';
        $this->showForm = false;
    }

    public function resetPassword(int $id): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);
        $this->validate(['password' => ['required', 'string', 'min:8']]);
        $user = User::findOrFail($id); $this->guardTarget($user); $user->update(['password' => Hash::make($this->password)]);
        $this->password = ''; $this->notice = 'Password reset successfully. Give the new password to the user securely.';
    }

    public function preparePasswordReset(int $id): void
    {
        $user = User::with('roles')->findOrFail($id);
        $this->guardTarget($user);
        $this->editingId = $id; $this->name = $user->name; $this->email = $user->email; $this->role = $user->roles->first()?->name ?: 'teacher'; $this->password = ''; $this->showForm = true;
    }

    public function render()
    {
        return view('livewire.admin.user-account-manager', ['users' => User::with('roles')->orderBy('name')->paginate(25), 'roles' => Role::whereIn('name', $this->assignableRoles())->orderBy('name')->get()])->layout('layouts.admin');
    }

    private function guardTarget(User $user): void
    {
        if ($user->hasRole('super-admin')) abort(403, 'Super Admin accounts are protected and cannot be controlled here.');
    }

    private function assignableRoles(): array
    {
        return Role::where('guard_name', 'web')->when(! auth()->user()->hasRole('super-admin'), fn ($q) => $q->where('name', '<>', 'super-admin'))->pluck('name')->all();
    }
}
