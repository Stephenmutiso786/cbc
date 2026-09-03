<?php

namespace App\Livewire\Admin;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Http\Request;

class StaffManager extends Component
{
    use WithFileUploads;

    public bool $showForm = false;
    public bool $showImport = false;
    public ?int $editingId = null;
    public $csvFile;
    public $signatureFile;
    public string $pasteNames = '';
    public array $form = ['staff_number' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'phone_number' => '', 'employment_type' => 'permanent', 'staff_type' => 'teaching', 'designation' => '', 'date_joined' => '', 'role' => 'teacher', 'password' => ''];
    public array $importErrors = [];
    public int $importedCount = 0;
    public array $importCredentials = [];
    public array $selectedIds = [];

    public function mount(Request $request): void
    {
        $this->showImport = $request->boolean('import') || $request->routeIs('admin.staff.import');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->signatureFile = null;
        $this->editingId = null;
        $this->form = ['staff_number' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'phone_number' => '', 'employment_type' => 'permanent', 'staff_type' => 'teaching', 'designation' => '', 'date_joined' => now()->format('Y-m-d'), 'role' => 'teacher', 'password' => ''];
        $this->showForm = true;
    }

    public function openImport(): void
    {
        $this->reset(['csvFile', 'pasteNames', 'importErrors', 'importedCount', 'importCredentials']);
        $this->showImport = true;
    }

    public function updatedSelectedIds(): void
    {
        $this->selectedIds = array_values(array_unique(array_map('intval', $this->selectedIds)));
    }

    public function selectAllStaff(): void
    {
        abort_unless($this->canDelete(), 403);
        $this->selectedIds = StaffMember::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function bulkDelete(): void
    {
        abort_unless($this->canDelete(), 403);
        $ids = array_values(array_filter(array_map('intval', $this->selectedIds)));
        abort_if($ids === [], 422, 'Select at least one staff member.');
        DB::transaction(function () use ($ids): void {
            StaffMember::whereIn('id', $ids)->with('user')->get()->each(function (StaffMember $member): void {
                $member->user?->syncRoles([]);
                $member->delete();
            });
        });
        $this->selectedIds = [];
        session()->flash('success', count($ids) . ' staff record(s) deleted.');
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $staff = StaffMember::with('user')->findOrFail($id);
        $this->editingId = $id;
        $this->signatureFile = null;
        $this->form = array_merge($staff->only(['staff_number', 'first_name', 'last_name', 'email', 'phone_number', 'employment_type', 'staff_type', 'designation', 'date_joined']), ['role' => $staff->user?->getRoleNames()->first() ?: 'teacher', 'password' => '']);
        $this->form['date_joined'] = $staff->date_joined?->format('Y-m-d');
        $this->showForm = true;
    }

    public function save(): void
    {
        $staff = $this->editingId ? StaffMember::findOrFail($this->editingId) : null;
        $data = $this->validate([
            'form.staff_number' => [$this->editingId ? 'required' : 'nullable', 'string', 'max:255', Rule::unique('staff_members', 'staff_number')->ignore($this->editingId)],
            'form.first_name' => ['required', 'string', 'max:255'], 'form.last_name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', Rule::unique('staff_members', 'email')->ignore($this->editingId)],
            'form.phone_number' => ['nullable', 'string', 'max:50'],
            'form.employment_type' => ['required', 'in:permanent,contract,bom,volunteer'], 'form.staff_type' => ['required', 'in:teaching,non_teaching'],
            'form.designation' => ['nullable', 'string', 'max:255'], 'form.date_joined' => ['required', 'date'],
            'form.role' => ['required', 'exists:roles,name'], 'form.password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
            'signatureFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ])['form'];
        if (! $this->editingId) {
            $data['staff_number'] = $this->newStaffNumber();
        }
        if ($this->duplicateNameExists($data['first_name'], $data['last_name'], $this->editingId)) {
            $this->addError('form.first_name', 'A staff member with the same name already exists.');
            return;
        }
        $signatureData = $this->signatureFile ? 'data:' . $this->signatureFile->getMimeType() . ';base64,' . base64_encode(file_get_contents($this->signatureFile->getRealPath())) : null;
        DB::transaction(function () use ($data, $staff, $signatureData) {
            $user = $staff?->user;
            if (!$user) $user = User::create(['name' => $data['first_name'] . ' ' . $data['last_name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'email_verified_at' => now()]);
            else {
                $user->update(['name' => $data['first_name'] . ' ' . $data['last_name'], 'email' => $data['email']]);
                if ($data['password']) $user->update(['password' => Hash::make($data['password'])]);
            }
            $user->syncRoles([$data['role']]);
            $staff = $staff ?: new StaffMember();
            $staff->fill(array_diff_key($data, array_flip(['role', 'password'])))->forceFill(['gender' => $staff->gender ?: 'male', 'user_id' => $user->id, 'is_active' => true])->save();
            if ($signatureData) $staff->update(['signature_data' => $signatureData]);
        });
        $this->signatureFile = null;
        $this->showForm = false;
        session()->flash('success', $this->editingId ? 'Staff record updated.' : 'Staff account created.');
    }

    public function importCsv(): void
    {
        $this->validate([
            'csvFile' => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'pasteNames' => ['nullable', 'string', 'max:50000'],
        ]);
        if (!$this->csvFile && trim($this->pasteNames) === '') {
            $this->addError('pasteNames', 'Paste staff names or choose a CSV file.');
            return;
        }

        $rows = $this->csvFile ? $this->readCsvRows() : $this->readPastedRows();
        $this->importErrors = []; $this->importedCount = 0;
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $row['role'] = $row['role'] ?: 'teacher';
            $row['password'] = $row['password'] ?: $this->newTemporaryPassword();
            $row['staff_number'] = $row['staff_number'] ?: $this->newStaffNumber();
            $row['email'] = $row['email'] ?: $this->newEmail($row['first_name'], $row['last_name']);
            $row['phone_number'] = $row['phone_number'] ?: null;
            $row['employment_type'] = $row['employment_type'] ?: 'permanent';
            $row['staff_type'] = $row['staff_type'] ?: 'teaching';
            $row['date_joined'] = $row['date_joined'] ?: now()->format('Y-m-d');
            if ($this->duplicateNameExists($row['first_name'], $row['last_name'])) {
                $this->importErrors[] = 'Row ' . $rowNumber . ': a staff member with the same name already exists.';
                continue;
            }
            $validator = validator($row, ['staff_number' => 'required|unique:staff_members,staff_number', 'first_name' => 'required|string|max:255', 'last_name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'phone_number' => 'nullable|string|max:50', 'employment_type' => 'required|in:permanent,contract,bom,volunteer', 'staff_type' => 'required|in:teaching,non_teaching', 'date_joined' => 'required|date', 'role' => 'required|exists:roles,name', 'password' => 'required|min:8']);
            if ($validator->fails()) { $this->importErrors[] = 'Row ' . $rowNumber . ': ' . implode(' ', $validator->errors()->all()); continue; }
            try {
                DB::transaction(function () use ($row) {
                    $user = User::create(['name' => $row['first_name'] . ' ' . $row['last_name'], 'email' => $row['email'], 'password' => Hash::make($row['password']), 'email_verified_at' => now()]);
                    $user->assignRole($row['role']);
                    StaffMember::create(array_merge($row, ['gender' => 'male', 'user_id' => $user->id, 'is_active' => true]));
                });
                $this->importedCount++;
                $this->importCredentials[] = ['name' => $row['first_name'] . ' ' . $row['last_name'], 'email' => $row['email'], 'password' => $row['password']];
            } catch (\Throwable $exception) {
                $this->importErrors[] = 'Row ' . $rowNumber . ': could not be saved (' . $exception->getMessage() . ').';
            }
        }
        if ($this->importedCount) {
            $credentials = collect($this->importCredentials)
                ->map(fn ($item) => "{$item['name']}: {$item['email']} / {$item['password']}")
                ->implode(' | ');
            session()->flash('success', "{$this->importedCount} staff account(s) imported. Login credentials: {$credentials}");
        }
        if (!$this->importErrors) $this->showImport = false;
    }

    private function readPastedRows(): array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', trim($this->pasteNames)))
            ->filter(fn ($line) => trim($line) !== '')
            ->values();
        if ($lines->isEmpty()) return [];

        $firstValues = str_contains($lines->first(), "\t")
            ? str_getcsv($lines->first(), "\t")
            : str_getcsv($lines->first());
        $headers = array_map(fn ($header) => $this->normaliseImportHeader($header), $firstValues);
        $hasHeader = count(array_intersect($headers, array_keys($this->importHeaderAliases()))) >= 2;

        if ($hasHeader) {
            return $this->rowsFromDelimitedLines($lines->skip(1)->all(), $headers);
        }

        return $lines->map(function ($line) {
            $parts = preg_split('/\s+/', trim($line));
            return $this->emptyImportRow(array_shift($parts) ?: '', $parts ? implode(' ', $parts) : '');
        })->values()->all();
    }

    private function readCsvRows(): array
    {
        $handle = fopen($this->csvFile->getRealPath(), 'r');
        $headers = array_map(fn ($value) => $this->normaliseImportHeader($value), fgetcsv($handle) ?: []);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            $rows[] = $this->mapImportValues($headers, $values);
        }
        fclose($handle);
        return $rows;
    }

    private function rowsFromDelimitedLines(array $lines, array $headers): array
    {
        return collect($lines)->map(function ($line) use ($headers) {
            $delimiter = str_contains($line, "\t") ? "\t" : ',';
            return $this->mapImportValues($headers, str_getcsv($line, $delimiter));
        })->filter(fn ($row) => count(array_filter($row, fn ($value) => trim((string) $value) !== '')) > 0)->values()->all();
    }

    private function mapImportValues(array $headers, array $values): array
    {
        $row = $this->emptyImportRow();
        $aliases = $this->importHeaderAliases();
        foreach ($headers as $position => $header) {
            $field = $aliases[$header] ?? null;
            if ($field && array_key_exists($field, $row)) {
                $row[$field] = trim((string) ($values[$position] ?? ''));
            }
        }
        return $row;
    }

    private function normaliseImportHeader(mixed $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', strtolower(trim((string) $header)));
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $header), '_');
    }

    private function importHeaderAliases(): array
    {
        return array_fill_keys([
            'staff_number', 'school_id', 'staff_id', 'first_name', 'firstname', 'last_name', 'lastname',
            'email', 'email_address', 'phone_number', 'phone', 'employment_type',
            'staff_type', 'designation', 'date_joined', 'role', 'password',
        ], null) + [
            'school_id' => 'staff_number', 'staff_id' => 'staff_number',
            'firstname' => 'first_name', 'lastname' => 'last_name',
            'email_address' => 'email', 'phone' => 'phone_number',
        ];
    }

    private function emptyImportRow(string $firstName = '', string $lastName = ''): array
    {
        return array_merge(array_fill_keys(['staff_number','first_name','last_name','email','phone_number','employment_type','staff_type','designation','date_joined','role','password'], ''), ['first_name' => $firstName, 'last_name' => $lastName]);
    }

    private function newStaffNumber(): string
    {
        do { $number = 'STF-' . str_pad((string) (StaffMember::withTrashed()->max('id') + random_int(1, 9)), 5, '0', STR_PAD_LEFT); }
        while (StaffMember::withTrashed()->where('staff_number', $number)->exists());
        return $number;
    }

    private function newEmail(string $firstName, string $lastName): string
    {
        do {
            $base = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim($firstName . '.' . $lastName))) . '.' . random_int(1000, 9999) . '@staff.local';
        } while (User::where('email', $base)->exists());
        return $base;
    }

    private function newTemporaryPassword(): string
    {
        return 'Kyandulu@' . random_int(100000, 999999);
    }

    private function duplicateNameExists(string $first, string $last, ?int $ignoreId = null): bool
    {
        $key = $this->nameKey($first, $last);
        return StaffMember::query()->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->get(['id', 'first_name', 'last_name'])
            ->contains(fn ($member) => $this->nameKey($member->first_name, $member->last_name) === $key);
    }

    private function nameKey(string $first, string $last): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $first . ' ' . $last)));
    }

    private function canDelete(): bool
    {
        return auth()->user()->can('manage staff');
    }

    public function render()
    {
        return view('livewire.admin.staff-manager', ['staff' => StaffMember::with('user')->orderBy('last_name')->paginate(25), 'roles' => \Spatie\Permission\Models\Role::orderBy('name')->get()])->layout('layouts.admin');
    }
}
