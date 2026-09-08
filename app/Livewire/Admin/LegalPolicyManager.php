<?php

namespace App\Livewire\Admin;

use App\Models\SchoolSetting;
use Livewire\Component;

class LegalPolicyManager extends Component
{
    public string $version = '';
    public string $terms = '';
    public string $privacy = '';
    public string $notice = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);
        $this->version = (string) SchoolSetting::where('key', 'legal_policy_version')->value('value') ?: config('legal.version');
        $this->terms = (string) SchoolSetting::where('key', 'legal_terms_content')->value('value');
        $this->privacy = (string) SchoolSetting::where('key', 'legal_privacy_content')->value('value');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);
        $this->validate([
            'version' => ['required', 'string', 'max:30'],
            'terms' => ['nullable', 'string', 'max:100000'],
            'privacy' => ['nullable', 'string', 'max:100000'],
        ]);

        SchoolSetting::updateOrCreate(['key' => 'legal_policy_version'], ['value' => $this->version]);
        SchoolSetting::updateOrCreate(['key' => 'legal_terms_content'], ['value' => $this->terms]);
        SchoolSetting::updateOrCreate(['key' => 'legal_privacy_content'], ['value' => $this->privacy]);
        config()->set('school.legal_policy_version', $this->version);
        $this->notice = 'Legal policies saved. Users will accept the new version before continuing.';
    }

    public function render()
    {
        return view('livewire.admin.legal-policy-manager')->layout('layouts.admin');
    }
}
