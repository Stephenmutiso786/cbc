<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManager extends Component
{
    public ?int $editingId = null;
    public string $name = '';
    public array $permissionIds = [];
    public string $notice = '';

    public function edit(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $role = Role::with('permissions')->findOrFail($id);
        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->permissionIds = $role->permissions->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'permissionIds', 'notice']);
    }

    public function save(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._ -]*$/i', 'unique:roles,name,' . ($this->editingId ?? 'NULL')],
            'permissionIds' => ['array'],
            'permissionIds.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = $this->editingId ? Role::findOrFail($this->editingId) : Role::create(['name' => strtolower(trim($this->name)), 'guard_name' => 'web']);
        if ($role->name === 'super-admin') {
            abort(403, 'The super-admin role is protected.');
        }
        $role->update(['name' => strtolower(trim($this->name))]);
        $role->syncPermissions(Permission::whereIn('id', $this->permissionIds)->get());
        $this->editingId = $role->id;
        $this->notice = 'Role permissions saved.';
    }

    public function delete(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $role = Role::findOrFail($id);
        abort_if(in_array($role->name, ['admin', 'super-admin', 'headteacher']), 403, 'Core roles are protected.');
        $role->delete();
        $this->resetForm();
        $this->notice = 'Role deleted.';
    }

    public function render()
    {
        return view('livewire.admin.role-manager', [
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(),
        ])->layout('layouts.admin');
    }

    private function canManage(): bool
    {
        return auth()->user()->can('manage roles');
    }
}
