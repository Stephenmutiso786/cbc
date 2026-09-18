<?php

namespace App\Livewire\Admin;

use App\Models\Learner;
use App\Models\User;
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

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'notice', 'learnerId']);
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
        ]);

        if ($data['role'] === 'learner' && ! $data['learnerId']) {
            $this->addError('learnerId', 'Select the learner record for this student account.');
            return;
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
            $user->email_verified_at ??= now();
            $user->save();
            $user->syncRoles([$data['role']]);

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

    public function resetPassword(int $id): void
    {
        abort_unless(auth()->user()->can('manage users'), 403);

        $user = User::with(['learner.guardians', 'staffMember', 'guardian', 'roles'])->findOrFail($id);
        $this->guardTarget($user);

        $plainPassword = app(LoginCredentialService::class)->defaultPasswordForUser($user);
        $user->forceFill([
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
        ])->save();

        $delivery = app(LoginCredentialService::class)->sendLoginDetails($user, $plainPassword);
        $this->password = '';
        $this->notice = $this->formatCredentialNotice($user, $plainPassword, $delivery, 'Password reset successfully.');
    }

    public function render()
    {
        return view('livewire.admin.user-account-manager', [
            'users' => User::with(['roles', 'learner'])->orderBy('name')->paginate(25),
            'roles' => Role::whereIn('name', $this->assignableRoles())->orderBy('name')->get(),
            'learners' => Learner::whereNull('user_id')->orWhereKey($this->learnerId)->orderBy('last_name')->orderBy('first_name')->get(),
        ])->layout('layouts.admin');
    }

    private function guardTarget(User $user, bool $abortIfProtected = true): bool
    {
        if ($user->hasRole('super-admin')) {
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
            ->when(! auth()->user()->hasRole('super-admin'), fn ($q) => $q->where('name', '<>', 'super-admin'))
            ->pluck('name')
            ->all();
    }

    private function formatCredentialNotice(User $user, string $password, array $delivery, string $lead): string
    {
        $login = app(LoginCredentialService::class)->loginIdentifier($user);
        $message = $lead . ' Login: ' . $login . '. Password: ' . $password . '.';

        if (($delivery['status'] ?? null) === 'sent') {
            $message .= ' SMS sent to ' . $delivery['recipient'] . '.';
        } elseif (($delivery['status'] ?? null) === 'failed') {
            $message .= ' SMS delivery failed for ' . ($delivery['recipient'] ?? 'the available contact') . '.';
        } else {
            $message .= ' No SMS recipient was available, so share the details securely.';
        }

        return $message;
    }
}
