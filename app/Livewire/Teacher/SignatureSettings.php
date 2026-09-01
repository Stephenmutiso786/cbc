<?php

namespace App\Livewire\Teacher;

use Livewire\Component;
use Livewire\WithFileUploads;

class SignatureSettings extends Component
{
    use WithFileUploads;

    public $signatureFile;
    public string $saved = '';

    public function save(): void
    {
        $this->validate(['signatureFile' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048']]);
        $staff = auth()->user()->staffMember;
        abort_unless($staff, 422, 'This account is not linked to a staff profile.');
        $staff->update(['signature_data' => 'data:' . $this->signatureFile->getMimeType() . ';base64,' . base64_encode(file_get_contents($this->signatureFile->getRealPath()))]);
        $this->reset('signatureFile');
        $this->saved = 'Your signature was saved and will appear on generated report cards.';
    }

    public function render()
    {
        return view('livewire.teacher.signature-settings')->layout('layouts.teacher');
    }
}
