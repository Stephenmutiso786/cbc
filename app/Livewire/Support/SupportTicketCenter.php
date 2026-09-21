<?php

namespace App\Livewire\Support;

use App\Models\SupportTicket;
use Livewire\Component;
use Livewire\WithPagination;

class SupportTicketCenter extends Component
{
    use WithPagination;

    public string $subject = '';
    public string $module = '';
    public string $device = '';
    public string $description = '';
    public string $errorMessage = '';
    public string $additionalInfo = '';
    public string $priority = 'normal';
    public ?int $editingId = null;
    public string $status = 'open';
    public string $diagnosticNotes = '';
    public string $resolution = '';

    private function isHandler(): bool { return auth()->user()->hasAnyRole(['super-admin', 'it-team']); }

    public function submit(): void
    {
        abort_unless(auth()->user()->can('submit support tickets'), 403);
        $this->validate([
            'subject' => ['required', 'string', 'max:180'],
            'module' => ['required', 'string', 'max:60'], 'device' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:10000'],
            'errorMessage' => ['nullable', 'string', 'max:2000'], 'additionalInfo' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);

        SupportTicket::create([
            'ticket_number' => 'TKT-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'created_by' => auth()->id(),
            'subject' => $this->subject,
            'module' => $this->module, 'device' => $this->device ?: null,
            'description' => $this->description,
            'error_message' => $this->errorMessage ?: null, 'additional_info' => $this->additionalInfo ?: null,
            'priority' => $this->priority,
            'status' => 'open',
        ]);
        $this->reset(['subject', 'module', 'device', 'description', 'errorMessage', 'additionalInfo']);
        $this->priority = 'normal';
        session()->flash('success', 'Support ticket submitted to the IT Team support queue.');
    }

    public function edit(int $id): void
    {
        abort_unless($this->isHandler(), 403);
        $ticket = SupportTicket::findOrFail($id);
        $this->editingId = $ticket->id;
        $this->status = $ticket->status;
        $this->diagnosticNotes = (string) $ticket->diagnostic_notes;
        $this->resolution = (string) $ticket->resolution;
    }

    public function updateTicket(): void
    {
        abort_unless($this->isHandler(), 403);
        $this->validate(['status' => ['required', 'in:open,investigating,resolved,closed']]);
        $ticket = SupportTicket::findOrFail($this->editingId);
        $ticket->update([
            'status' => $this->status,
            'diagnostic_notes' => $this->diagnosticNotes ?: null,
            'resolution' => $this->resolution ?: null,
            'assigned_to' => auth()->id(),
            'closed_at' => in_array($this->status, ['resolved', 'closed'], true) ? now() : null,
        ]);
        $this->reset(['editingId', 'diagnosticNotes', 'resolution']);
        $this->status = 'open';
        session()->flash('success', 'Ticket updated and assigned to the IT Team queue.');
    }

    public function render()
    {
        $isSuperAdmin = $this->isHandler();
        $tickets = SupportTicket::with(['creator', 'assignee', 'school'])
            ->when(! $isSuperAdmin, fn ($query) => $query->where('created_by', auth()->id()))
            ->latest()->paginate(15);

        $layout = 'layouts.teacher';
        if ($isSuperAdmin) {
            $layout = 'layouts.app';
        } elseif (request()->routeIs('admin.*')) {
            $layout = 'layouts.admin';
        } elseif (request()->routeIs('student.*')) {
            $layout = 'layouts.student';
        } elseif (request()->routeIs('parent.*')) {
            $layout = 'layouts.parent';
        } elseif (request()->routeIs('finance.*')) {
            $layout = 'layouts.finance';
        }

        return view('livewire.support.ticket-center', compact('tickets', 'isSuperAdmin'))->layout($layout);
    }
}
