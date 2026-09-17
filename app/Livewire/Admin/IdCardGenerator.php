<?php

namespace App\Livewire\Admin;

use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\StaffMember;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class IdCardGenerator extends Component
{
    use WithFileUploads, WithPagination;

    public string $tab = 'learners'; // learners | staff
    public ?int $classFilter = null;
    public array $selected = [];
    public array $photoUploads = []; // [modelId => TemporaryUploadedFile]
    public string $docType = 'id_card'; // id_card | certificate
    public string $certificateType = 'completion';

    public function updatingTab(): void
    {
        $this->selected = [];
        $this->resetPage();
    }

    public function toggleAll(bool $value): void
    {
        $ids = $this->tab === 'learners'
            ? $this->learnersQuery()->pluck('id')->all()
            : StaffMember::where('is_active', true)->pluck('id')->all();

        $this->selected = $value ? $ids : [];
    }

    public function uploadPhoto(int $id): void
    {
        $this->validate(['photoUploads.' . $id => ['image', 'max:2048']]);

        $file = $this->photoUploads[$id] ?? null;
        if (! $file) {
            return;
        }

        $path = $file->store('photos/' . $this->tab, 'public');
        $model = $this->tab === 'learners' ? Learner::findOrFail($id) : StaffMember::findOrFail($id);
        if ($model->photo_path) {
            Storage::disk('public')->delete($model->photo_path);
        }
        $model->update(['photo_path' => $path]);
        unset($this->photoUploads[$id]);
    }

    public function generate()
    {
        if (empty($this->selected)) {
            session()->flash('error', 'Select at least one person first.');
            return;
        }

        $records = $this->tab === 'learners'
            ? Learner::with('schoolClass')->whereIn('id', $this->selected)->get()
            : StaffMember::whereIn('id', $this->selected)->get();

        $view = $this->docType === 'certificate' ? 'pdf.certificates' : 'pdf.id-cards';
        $pdf = Pdf::loadView($view, [
            'records' => $records,
            'type' => $this->tab,
            'certificateType' => $this->certificateType,
        ])->setPaper('a4');

        $filename = ($this->docType === 'certificate' ? 'certificates' : 'id-cards') . '-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    private function learnersQuery()
    {
        return Learner::query()->where('is_active', true)
            ->when($this->classFilter, fn ($q) => $q->where('class_id', $this->classFilter));
    }

    public function render()
    {
        $items = $this->tab === 'learners'
            ? $this->learnersQuery()->with('schoolClass')->orderBy('first_name')->paginate(20)
            : StaffMember::where('is_active', true)->orderBy('first_name')->paginate(20);

        return view('livewire.admin.id-card-generator', [
            'items' => $items,
            'classes' => SchoolClass::orderBy('name')->get(),
        ])->layout('layouts.admin');
    }
}

