<?php

namespace App\Livewire\Admin;

use App\Models\Learner;
use App\Models\School;
use App\Models\User;
use App\Models\SystemLog;
use App\Services\LoginCredentialService;
use Illuminate\Support\Facades\DB;
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
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'teacher';
    public string $notice = '';
    public string $learnerId = '';
    public ?int $resettingPasswordFor = null;
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';
    public string $schoolId = '';
    public string $search = '';
    public string $roleFilter = '';
    public string $statusFilter = '';
    public string $schoolFilter = '';
    public string $createdFrom = '';
    public string $createdTo = '';
    public int $perPage = 25;
    public array $selectedUserIds = [];
    public string $bulkAction = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'notice', 'learnerId', 'schoolId']);
        $this->role = 'teacher';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::with(['roles', 'learner', 'staffMember', 'guardian'])->findOrFail($id);
        $this->guardTarget($user);

        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->roles->first()?->name ?: 'teacher';
        $this->learnerId = (string) ($user->learner?->id ?? '');
        $this->schoolId = (string) ($user->school_id ?? '');
        $this->password = '';
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'learnerId' => ['nullable', 'integer', 'exists:learners,id'],
            'schoolId' => ['nullable', 'integer', 'exists:schools,id'],
        ]);

        if (auth()->user()->hasRole('super-admin') && ! in_array($data['role'], ['super-admin', 'it-team'], true) && ! $data['schoolId']) {
            $this->addError('schoolId', 'Select the school this account belongs to.');
            return;
        }

        if ($data['role'] === 'learner' && ! $data['learnerId']) {
            $this->addError('learnerId', 'Select the learner record for this student account.');
            return;
        }

        // A learner account is always part of the learner's institution.  Do
        // not let a platform operator accidentally join a learner from one
        // school to an account displayed under another school.
        if ($data['role'] === 'learner' && $data['learnerId'] && auth()->user()->hasRole('super-admin')) {
            $learnerSchoolId = Learner::withoutSchoolScope()->whereKey($data['learnerId'])->value('school_id');
            if ((string) $learnerSchoolId !== (string) $data['schoolId']) {
                $this->addError('schoolId', 'The selected learner belongs to a different school.');
                return;
            }
        }

        $linkedLearner = Learner::query()
            ->whereKey($data['learnerId'])
            ->whereNotNull('user_id')
            ->when($this->editingId, fn ($query) => $query->where('user_id', '!=', $this->editingId))
            ->exists();

        if ($data['learnerId'] && $linkedLearner) {
            $this->addError('learnerId', 'That learner is already linked to another account.');
            return;
        }

        $plainPassword = null;

        $user = DB::transaction(function () use ($data, &$plainPassword) {
            $user = $this->editingId
                ? User::with(['learner.guardians', 'staffMember', 'guardian', 'roles'])->findOrFail($this->editingId)
                : new User();

            $this->guardTarget($user);

            $user->name = $data['name'];
            $user->email = $data['email'];
            if (auth()->user()->hasRole('super-admin')) {
                $user->school_id = in_array($data['role'], ['super-admin', 'it-team'], true) ? null : $data['schoolId'];
            }
            $user->status ??= 'active';
            $user->email_verified_at ??= now();
            // The users table requires a password at insert time. Previously
            // this was assigned only after the first save, so platform-only
            // IT-team accounts failed with a database 500 before roles could
            // be attached.
            if (! $this->editingId) {
                $plainPassword = (string) $data['password'];
                $user->password = Hash::make($plainPassword);
                $user->must_change_password = true;
            }
            $user->save();
            $user->syncRoles([$data['role']]);

            // Accounts created from User Accounts can represent an existing
            // staff record. Keep the operational teacher profile linked so
            // allocations, marks, timetable and results use the same person.
            if ($data['role'] !== 'learner') {
                $user->unsetRelation('staffMember');
                $user->resolvedStaffMember();
            }

            Learner::where('user_id', $user->id)->update(['user_id' => null]);

            if ($data['role'] === 'learner') {
                $learner = Learner::whereKey($data['learnerId'])->firstOrFail();
                $learner->forceFill(['user_id' => $user->id])->saveQuietly();

                $plainPassword = trim((string) $data['password']) !== ''
                    ? (string) $data['password']
                    : app(LoginCredentialService::class)->defaultPasswordForLearner($learner);
            } elseif (! $this->editingId || trim((string) $data['password']) !== '') {
                $plainPassword = trim((string) $data['password']) !== ''
                    ? (string) $data['password']
                    : app(LoginCredentialService::class)->defaultPasswordForUser($user);
            }

            if ($plainPassword !== null) {
                $user->forceFill([
                    'password' => Hash::make($plainPassword),
                    'must_change_password' => true,
                ])->save();
            }

            return $user->loadMissing(['learner.guardians', 'staffMember', 'guardian', 'roles']);
        });

        if ($plainPassword !== null) {
            $delivery = app(LoginCredentialService::class)->sendLoginDetails($user, $plainPassword);
            $this->notice = $this->formatCredentialNotice(
                $user,
                $plainPassword,
                $delivery,
                $this->editingId ? 'User account updated and password refreshed.' : 'User account created with default login details.'
            );
        } else {
            $this->notice = 'User account updated.';
        }

        $this->showForm = false;
    }

    public function updated($property): void
    {
        if ($property === 'role') {
            // Platform operators are not tenants.  Clear a previously chosen
            // school when the form is switched to either platform role so an
            // IT-team account can never be created against a school.
            if (in_array($this->role, ['super-admin', 'it-team'], true)) {
                $this->schoolId = '';
            }

            if ($this->role !== 'learner') {
                $this->learnerId = '';
            }
        }

        if (in_array($property, ['search', 'roleFilter', 'statusFilter', 'schoolFilter', 'createdFrom', 'createdTo', 'perPage'], true)) {
            $this->resetPage();
            $this->selectedUserIds = [];
        }
    }

    public function changeStatus(int $id, string $status): void
    {
        abort_unless(in_array($status, ['active', 'suspended', 'inactive'], true), 422);
        $user = User::with('roles')->findOrFail($id);
        $this->guardTarget($user);
        $user->forceFill(['status' => $status])->save();
        $this->auditLifecycleChange($user, $status);
        $this->notice = "{$user->name} is now {$status}.";
    }

    public function applyBulkAction(): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);
        if (! in_array($this->bulkAction, ['active', 'suspended', 'inactive'], true)) {
            $this->addError('bulkAction', 'Choose an account status action.');
            return;
        }
        $users = User::with('roles')->whereIn('id', $this->selectedUserIds)->get();
        $changed = 0;
        foreach ($users as $user) {
            if ($this->guardTarget($user, false)) {
                $user->forceFill(['status' => $this->bulkAction])->save();
                $this->auditLifecycleChange($user, $this->bulkAction);
                $changed++;
            }
        }
        $this->selectedUserIds = [];
        $this->bulkAction = '';
        $this->notice = $changed ? "Updated {$changed} account(s)." : 'No eligible accounts were selected.';
    }

    public function openPasswordReset(int $id): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);

        $user = User::with('roles')->findOrFail($id);
        $this->guardTarget($user);
        $this->resetValidation();
        $this->resettingPasswordFor = $user->id;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
    }

    public function setPassword(): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);

        $data = $this->validate([
            'resettingPasswordFor' => ['required', 'integer'],
            'newPassword' => ['required', 'string', 'min:8', 'same:newPasswordConfirmation'],
            'newPasswordConfirmation' => ['required', 'string'],
        ]);

        $user = User::with('roles')->findOrFail($data['resettingPasswordFor']);
        $this->guardTarget($user);

        $user->forceFill([
            'password' => Hash::make($data['newPassword']),
            'must_change_password' => true,
        ])->save();

        $delivery = app(LoginCredentialService::class)->sendLoginDetails($user, $data['newPassword']);

        SystemLog::create([
            'user_id' => auth()->id(), 'method' => 'PASSWORD_RESET', 'path' => 'admin/user-accounts/' . $user->id,
            'status' => 200, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            'context' => ['target_user_id' => $user->id, 'target_email' => $user->email],
        ]);

        $this->reset(['resettingPasswordFor', 'newPassword', 'newPasswordConfirmation']);
        $this->notice = $this->formatCredentialNotice(
            $user,
            $data['newPassword'],
            $delivery,
            "Password reset for {$user->name}. They must create a personal password at their next sign-in."
        );
    }

    public function render()
    {
        return view('livewire.admin.user-account-manager', [
            'users' => User::with(['roles', 'learner', 'school'])
                ->when($this->search !== '', function ($query) {
                    $term = '%' . trim($this->search) . '%';
                    $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
                })
                ->when($this->roleFilter !== '', fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $this->roleFilter)))
                ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
                ->when(auth()->user()->hasRole('super-admin') && $this->schoolFilter !== '', fn ($query) => $this->schoolFilter === '0' ? $query->whereNull('school_id') : $query->where('school_id', $this->schoolFilter))
                ->when($this->createdFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->createdFrom))
                ->when($this->createdTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->createdTo))
                ->orderBy('name')->paginate(in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25),
            'roles' => Role::whereIn('name', $this->assignableRoles())->orderBy('name')->get(),
            'learners' => Learner::whereNull('user_id')
                ->when($this->learnerId !== '', fn ($query) => $query->orWhere('id', $this->learnerId))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'schools' => auth()->user()->hasRole('super-admin') ? School::orderBy('name')->get() : collect(),
        ])->layout('layouts.admin');
    }

    private function guardTarget(User $user, bool $abortIfProtected = true): bool
    {
        // Direct Livewire requests can carry an arbitrary ID.  The model's
        // school scope handles normal tenant requests; this explicit check
        // keeps the rule intact when the current tenant is resolved later in
        // the request lifecycle.
        if (! auth()->user()->hasRole('super-admin') && $user->exists && (string) $user->school_id !== (string) auth()->user()->school_id) {
            if ($abortIfProtected) {
                abort(403, 'You can only manage accounts in your own school.');
            }

            return false;
        }

        if ($user->hasRole('super-admin') || (! auth()->user()->hasRole('super-admin') && $user->hasRole('it-team'))) {
            if ($abortIfProtected) {
                abort(403, 'Super Admin accounts are protected and cannot be controlled here.');
            }

            return false;
        }

        return true;
    }

    private function assignableRoles(): array
    {
        return Role::where('guard_name', 'web')
            ->when(! auth()->user()->hasRole('super-admin'), fn ($q) => $q->whereNotIn('name', ['super-admin', 'it-team']))
            ->pluck('name')
            ->all();
    }

    private function formatCredentialNotice(User $user, string $password, array $delivery, string $lead): string
    {
        $login = app(LoginCredentialService::class)->loginIdentifier($user);
        // The operator either supplied the password or it was delivered by
        // SMS. Never render a reusable credential back into a Livewire page.
        $message = $lead . ' Login: ' . $login . '.';

        if (($delivery['status'] ?? null) === 'sent') {
            $message .= ' SMS sent to ' . $delivery['recipient'] . '.';
        } elseif (($delivery['status'] ?? null) === 'failed') {
            $message .= ' SMS delivery failed for ' . ($delivery['recipient'] ?? 'the available contact') . '.';
        } else {
            $message .= ' No SMS recipient was available, so share the details securely.';
        }

        return $message;
    }

    private function auditLifecycleChange(User $user, string $status): void
    {
        SystemLog::create([
            'user_id' => auth()->id(), 'method' => 'ACCOUNT_STATUS_CHANGED', 'path' => 'admin/user-accounts/' . $user->id,
            'status' => 200, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            'context' => ['target_user_id' => $user->id, 'target_email' => $user->email, 'account_status' => $status],
        ]);
    }
}
