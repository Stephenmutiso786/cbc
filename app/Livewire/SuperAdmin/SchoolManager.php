<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Package;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\OlympusSmsService;
use App\Services\SchoolAcademicSetupService;
use App\Support\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class SchoolManager extends Component
{
    use WithPagination;

    public bool $showForm = false;
    public ?int $editingId = null;

    public array $form = [
        'name' => '', 'type' => 'primary', 'motto' => '', 'address' => '',
        'phone' => '', 'email' => '', 'is_active' => true,
    ];

    // Only used when creating a brand new school.
    public string $adminName = '';
    public string $adminEmail = '';
    public string $adminPhone = '';
    public ?string $generatedPassword = null;
    public ?string $smsStatus = null;

    // Manual plan grant (e.g. cash/bank payment made outside M-Pesa).
    public bool $showPlanForm = false;
    public ?int $planSchoolId = null;
    public ?int $grantPackageId = null;
    public string $grantExpiresAt = '';
    public string $grantNote = '';

    // SMS units are paid for by the school, then issued only by a
    // super-admin after the payment has been confirmed.
    public bool $showSmsForm = false;
    public ?int $smsSchoolId = null;
    public string $smsUnits = '';
    public string $smsAmountPaid = '';
    public string $smsPaymentReference = '';
    public string $smsNote = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->generatedPassword = null;
        $this->smsStatus = null;
        $this->form = ['name' => '', 'type' => 'primary', 'motto' => '', 'address' => '', 'phone' => '', 'email' => '', 'is_active' => true];
        $this->adminName = '';
        $this->adminEmail = '';
        $this->adminPhone = '';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $school = School::findOrFail($id);
        $this->editingId = $school->id;
        $this->generatedPassword = null;
        $this->form = [
            'name' => $school->name, 'type' => $school->type, 'motto' => (string) $school->motto,
            'address' => (string) $school->address, 'phone' => (string) $school->phone,
            'email' => (string) $school->email, 'is_active' => (bool) $school->is_active,
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $rules = [
            'form.name' => ['required', 'string', 'max:255'],
            'form.type' => ['required', 'in:primary,secondary,mixed'],
            'form.motto' => ['nullable', 'string', 'max:255'],
            'form.address' => ['nullable', 'string', 'max:500'],
            'form.phone' => ['nullable', 'string', 'max:50'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.is_active' => ['boolean'],
        ];

        if (! $this->editingId) {
            $rules['adminName'] = ['required', 'string', 'max:255'];
            $rules['adminEmail'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')];
            $rules['adminPhone'] = ['required', 'string', 'min:9', 'max:15'];
        }

        $this->validate($rules);

        if ($this->editingId) {
            $school = School::findOrFail($this->editingId);
            $school->update($this->form);
            // Keep the school's own Settings page showing the same details
            // the super-admin just edited here.
            Tenant::run($school->id, fn () => $this->syncSchoolSettings($this->form));
            $this->showForm = false;
            session()->flash('success', 'School updated.');
            return;
        }

        $slug = Str::slug($this->form['name']);
        if (School::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        $school = School::create([
            ...$this->form,
            'slug' => $slug,
            'school_code' => $this->nextSchoolCode($this->form['name']),
        ]);

        $password = Str::password(12);

        // Create the school's first admin *inside* this school's tenant
        // context, so BelongsToSchool stamps school_id correctly — and seed
        // the school's own Settings page with what was just entered here,
        // so it shows the right name/details from day one.
        $admin = Tenant::run($school->id, function () use ($password, $school) {
            $this->syncSchoolSettings($this->form);
            app(SchoolAcademicSetupService::class)->initializeCurrentSchool();

            $user = User::create([
                'name' => $this->adminName,
                'email' => $this->adminEmail,
                'password' => Hash::make($password),
                // Set this explicitly instead of relying solely on the
                // tenant model event. It is the security boundary that keeps
                // the new admin, its name/branding and its classes together.
                'school_id' => $school->id,
                'email_verified_at' => now(),
            ]);
            $user->assignRole('school-admin');

            return $user;
        });

        $this->generatedPassword = $password;
        $this->smsStatus = $this->sendCredentialsSms($school, $password);
        session()->flash('success', "School \"{$school->name}\" created with Grade 1–9 classes, subjects and grading scales. Share the admin login below with them once — it will not be shown again.");
    }

    /** Keeps the school's own Settings page in step with what's entered here. */
    private function syncSchoolSettings(array $form): void
    {
        foreach (['name', 'motto', 'address', 'phone', 'email', 'type'] as $key) {
            if (($form[$key] ?? '') !== '') {
                SchoolSetting::updateOrCreate(['key' => $key], ['value' => $form[$key]]);
            }
        }
    }

    /** A stable, human-readable school identifier for tenant onboarding. */
    private function nextSchoolCode(string $name): string
    {
        $words = preg_split('/[^[:alnum:]]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $base = Str::upper(collect($words)->map(fn (string $word) => Str::substr($word, 0, 1))->implode(''));
        $base = Str::limit($base ?: 'SCHOOL', 10, '');
        $candidate = $base;

        while (School::where('school_code', $candidate)->exists()) {
            $candidate = $base . '-' . Str::upper(Str::random(4));
        }

        return $candidate;
    }

    /**
     * The new school has no SMS credits yet (nothing paid for), so this is
     * sent directly through the platform's own SMS account as an onboarding
     * cost — it never touches the school's own credit wallet.
     */
    private function sendCredentialsSms(School $school, string $password): ?string
    {
        try {
            app(OlympusSmsService::class)->sendSms(
                $this->adminPhone,
                "Welcome to ElimuHub! Your {$school->name} admin account is ready.\n"
                . "Email: {$this->adminEmail}\n"
                . "Password: {$password}\n"
                . "Login and change your password immediately."
            );
            return 'sent';
        } catch (\Throwable $e) {
            Log::warning('Failed to SMS new school-admin credentials', ['school_id' => $school->id, 'error' => $e->getMessage()]);
            return 'failed';
        }
    }

    public function toggleActive(int $id): void
    {
        $school = School::findOrFail($id);
        $school->update(['is_active' => ! $school->is_active]);
    }

    public function toggleLock(int $id): void
    {
        $school = School::findOrFail($id);
        $school->update(['is_locked' => ! $school->is_locked]);
        session()->flash('success', $school->is_locked ? "{$school->name} is now locked." : "{$school->name} is unlocked and may access the platform.");
    }

    public function openPlanForm(int $schoolId): void
    {
        $school = School::findOrFail($schoolId);
        $this->planSchoolId = $schoolId;
        $this->grantPackageId = $school->package_id;
        $this->grantExpiresAt = optional($school->package_expires_at)->toDateString() ?? now()->addMonths(4)->toDateString();
        $this->grantNote = '';
        $this->showPlanForm = true;
    }

    public function grantPlan(): void
    {
        $this->validate([
            'grantPackageId' => ['required', 'exists:packages,id'],
            'grantExpiresAt' => ['required', 'date'],
            'grantNote' => ['nullable', 'string', 'max:255'],
        ]);

        $school = School::findOrFail($this->planSchoolId);
        $package = Package::findOrFail($this->grantPackageId);
        $school->assignPackage($package, $this->grantExpiresAt, auth()->id(), $this->grantNote ?: 'Manually granted by super-admin');

        $this->showPlanForm = false;
        session()->flash('success', "Plan updated for \"{$school->name}\".");
    }

    public function openSmsForm(int $schoolId): void
    {
        $this->smsSchoolId = School::findOrFail($schoolId)->id;
        $this->smsUnits = '';
        $this->smsAmountPaid = '';
        $this->smsPaymentReference = '';
        $this->smsNote = '';
        $this->resetValidation();
        $this->showSmsForm = true;
    }

    public function allocateSmsCredits(): void
    {
        $this->validate([
            'smsSchoolId' => ['required', 'exists:schools,id'],
            'smsUnits' => ['required', 'integer', 'min:1', 'max:1000000'],
            'smsAmountPaid' => ['required', 'numeric', 'gt:0'],
            'smsPaymentReference' => ['required', 'string', 'max:100'],
            'smsNote' => ['nullable', 'string', 'max:255'],
        ]);

        $school = School::findOrFail($this->smsSchoolId);
        $school->allocateSmsCredits(
            (int) $this->smsUnits,
            $this->smsAmountPaid === '' ? null : (float) $this->smsAmountPaid,
            $this->smsPaymentReference ?: null,
            auth()->id(),
            $this->smsNote ?: 'SMS payment confirmed by super-admin',
        );

        $this->showSmsForm = false;
        session()->flash('success', "{$this->smsUnits} SMS credits allocated to \"{$school->name}\" after payment confirmation.");
    }

    public function render()
    {
        return view('livewire.super-admin.school-manager', [
            'schools' => School::with('package')->withCount(['users', 'learners'])->orderBy('name')->paginate(15),
            'packages' => Package::where('is_active', true)->orderBy('price')->get(),
        ])->layout('layouts.admin');
    }
}
