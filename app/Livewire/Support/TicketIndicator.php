<?php

namespace App\Livewire\Support;

use App\Models\SupportTicket;
use Livewire\Component;

class TicketIndicator extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);

        $count = SupportTicket::query()
            ->where('status', 'open')
            ->count();

        return view('livewire.support.ticket-indicator', compact('count'));
    }
}
