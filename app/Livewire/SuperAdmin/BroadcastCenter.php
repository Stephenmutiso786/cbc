<?php

namespace App\Livewire\SuperAdmin;

use App\Jobs\SendSmsJob;
use App\Models\PlatformBroadcast;
use App\Models\School;
use App\Models\SchoolNotification;
use App\Support\Tenant;
use Livewire\Component;

class BroadcastCenter extends Component
{
    public string $title = '';
    public string $message = '';
    public bool $sendSms = false;
    public bool $pinBanner = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    }

    public function send(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $schools = School::where('is_active', true)->get();

        $broadcast = PlatformBroadcast::create([
            'title' => $this->title,
            'message' => $this->message,
            'send_sms' => $this->sendSms,
            'is_pinned' => $this->pinBanner,
            'status' => 'sending',
            'created_by' => auth()->id(),
            'total_schools' => $schools->count(),
        ]);

        $notified = 0;
        foreach ($schools as $school) {
            Tenant::run($school->id, function () use ($school, $broadcast, &$notified) {
                if ($this->sendSms) {
                    // Reuses the existing guardian fan-out + SMS-credit-wallet
                    // deduction already built for regular school notifications —
                    // each school's own credit balance pays for its own guardians.
                    $notification = SchoolNotification::create([
                        'sender_id'   => null,
                        'broadcast_id' => $broadcast->id,
                        'title'       => $broadcast->title,
                        'message'     => $broadcast->message,
                        'type'        => 'general',
                        'channel'     => 'sms',
                        'status'      => 'draft',
                    ]);
                    SendSmsJob::dispatch($notification->id);
                }
                $notified++;
            });
        }

        $broadcast->update(['status' => 'sent', 'sent_at' => now(), 'schools_notified' => $notified]);

        $this->reset(['title', 'message', 'sendSms', 'pinBanner']);
        session()->flash('success', "Broadcast sent to {$notified} school(s).");
    }

    public function render()
    {
        return view('livewire.super-admin.broadcast-center', [
            'broadcasts' => PlatformBroadcast::latest()->limit(20)->get(),
        ])->layout('layouts.admin');
    }
}
