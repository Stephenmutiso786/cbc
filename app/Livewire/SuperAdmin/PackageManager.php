<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Package;
use Livewire\Component;

class PackageManager extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;

    public array $form = [
        'name' => '', 'description' => '', 'price' => '', 'billing_cycle' => 'termly',
        'max_students' => '', 'max_staff' => '', 'is_active' => true,
    ];

    /** @var array<string,bool> */
    public array $features = [];

    public const AVAILABLE_FEATURES = [
        'fees'          => 'Fees & Payments',
        'inventory'     => 'Inventory Management',
        'notifications' => 'SMS Notifications to Guardians',
        'kemis'         => 'KEMIS Integration',
        'lesson_plans'  => 'Lesson Plans',
        'portfolio'     => 'Learner Portfolios',
        'timetable'     => 'Timetabling',
        'id_cards'      => 'ID Cards & Certificates',
        'predictive_analytics' => 'AI Risk Prediction',
        'newsletters' => 'School Newsletters',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name' => '', 'description' => '', 'price' => '', 'billing_cycle' => 'termly', 'max_students' => '', 'max_staff' => '', 'is_active' => true];
        $this->features = array_fill_keys(array_keys(self::AVAILABLE_FEATURES), false);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $package = Package::findOrFail($id);
        $this->editingId = $package->id;
        $this->form = [
            'name' => $package->name, 'description' => (string) $package->description,
            'price' => (string) $package->price, 'billing_cycle' => $package->billing_cycle,
            'max_students' => (string) $package->max_students, 'max_staff' => (string) $package->max_staff,
            'is_active' => (bool) $package->is_active,
        ];
        $this->features = array_fill_keys(array_keys(self::AVAILABLE_FEATURES), false);
        foreach ($package->features ?? [] as $key) {
            $this->features[$key] = true;
        }
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string', 'max:1000'],
            'form.price' => ['required', 'numeric', 'min:0'],
            'form.billing_cycle' => ['required', 'in:monthly,termly,yearly'],
            'form.max_students' => ['nullable', 'integer', 'min:1'],
            'form.max_staff' => ['nullable', 'integer', 'min:1'],
        ]);

        $payload = [
            ...$this->form,
            'sms_credits_granted' => 0,
            'max_students' => $this->form['max_students'] !== '' ? (int) $this->form['max_students'] : null,
            'max_staff' => $this->form['max_staff'] !== '' ? (int) $this->form['max_staff'] : null,
            'features' => array_keys(array_filter($this->features)),
        ];

        if ($this->editingId) {
            Package::findOrFail($this->editingId)->update($payload);
        } else {
            Package::create($payload);
        }

        $this->showForm = false;
        session()->flash('success', 'Plan saved.');
    }

    public function toggleActive(int $id): void
    {
        $package = Package::findOrFail($id);
        $package->update(['is_active' => ! $package->is_active]);
    }

    public function render()
    {
        return view('livewire.super-admin.package-manager', [
            'packages' => Package::withCount('schools')->orderBy('price')->get(),
        ])->layout('layouts.admin');
    }
}
