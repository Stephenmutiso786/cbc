<?php

namespace App\Livewire\Support;

use App\Models\SupportTicket;
use Livewire\Component;
use Livewire\WithPagination;

class SupportTicketCenter extends Component
{
    use WithPagination;

    public string $subject = '';
    public string $description = '';
    public string $priority = 'normal';
    public ?int $editingId = null;
    public string $status = 'open';
    public string $diagnosticNotes = '';
    public string $resolution = '';

    public function submit(): void
    {
        abort_unless(auth()->user()->can('submit support tickets'), 403);
        $this->validate([
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);

        SupportTicket::create([
            'ticket_number' => 'TKT-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'created_by' => auth()->id(),
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => 'open',
        ]);
        $this->reset(['subject', 'description']);
        $this->priority = 'normal';
        session()->flash('success', 'Support ticket submitted to the Super Admin support queue.');
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);
        $ticket = SupportTicket::findOrFail($id);
        $this->editingId = $ticket->id;
        $this->status = $ticket->status;
        $this->diagnosticNotes = (string) $ticket->diagnostic_notes;
        $this->resolution = (string) $ticket->resolution;
    }

    public function updateTicket(): void
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);
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
        session()->flash('success', 'Ticket updated and assigned to the Super Admin queue.');
    }

    public function render()
    {
        $isSuperAdmin = auth()->user()->hasRole('super-admin');
        $tickets = SupportTicket::with(['creator', 'assignee'])
            ->when(! $isSuperAdmin, fn ($query) => $query->where('created_by', auth()->id()))
            ->latest()->paginate(15);

        $layout = $isSuperAdmin || request()->routeIs('admin.*')
            ? 'layouts.admin'
            : (request()->routeIs('parent.*') ? 'layouts.parent' : (request()->routeIs('finance.*') ? 'layouts.finance' : 'layouts.teacher'));

        return view('livewire.support.ticket-center', compact('tickets', 'isSuperAdmin'))->layout($layout);
    }
}
