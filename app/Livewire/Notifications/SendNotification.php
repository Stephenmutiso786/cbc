<?php

namespace App\Livewire\Notifications;

use App\Jobs\SendSmsJob;
use App\Models\Guardian;
use App\Models\SchoolNotification;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SendNotification extends Component
{
    public string $title        = '';
    public string $message      = '';
    public string $type         = 'general';
    public string $channel      = 'sms';
    public string $targetGrade  = '';
    public string $targetGroup  = 'all'; // all | grade | boarding | day
    public ?int $targetClassId = null;

    public bool $sending  = false;
    public bool $sent     = false;
    public int  $count    = 0;
    public string $flash  = '';

    protected $rules = [
        'title'   => 'required|string|max:100',
        'message' => 'required|string|max:480',
        'channel' => 'required|in:sms,email,push,all',
        'type'    => 'required|in:general,fees,exam,report_card,attendance,emergency',
    ];

    public function getRecipientsCount(): int
    {
        return $this->recipientQuery()->count();
    }

    public function updatedTargetGrade(): void  { $this->count = $this->getRecipientsCount(); }
    public function updatedTargetGroup(): void  { $this->count = $this->getRecipientsCount(); }
    public function updatedTargetClassId(): void { $this->count = $this->getRecipientsCount(); }

    public function send(): void
    {
        $this->validate();
        $this->sending = true;

        $staff = Auth::user()->staffMember;
        if (!$staff) {
            $this->addError('message', 'This account is not linked to a staff profile.');
            $this->sending = false;
            return;
        }

        $isAdmin = Auth::user()->hasAnyRole(['admin', 'super-admin', 'principal', 'deputy-principal', 'hod']);
        if (!$isAdmin && !$this->targetClassId) {
            $this->addError('targetClassId', 'Select the class whose parents should receive this message.');
            $this->sending = false;
            return;
        }
        if (!$isAdmin && !\App\Models\TeacherSubjectAllocation::where('teacher_id', $staff->id)->where('class_id', $this->targetClassId)->where('is_active', true)->exists()) {
            abort(403, 'You are not allocated to this class.');
        }

        $notification = SchoolNotification::create([
            'sender_id'         => $staff->id,
            'title'             => $this->title,
            'message'           => $this->message,
            'type'              => $this->type,
            'channel'           => $this->channel,
            'target_grade'      => $this->targetGrade ?: null,
            'total_recipients'  => $this->getRecipientsCount(),
            'status'            => 'queued',
            'scheduled_at'      => now(),
        ]);

        // Dispatch background job
        SendSmsJob::dispatch($notification->id, $this->targetGrade, $this->targetGroup, $this->targetClassId);

        $this->sent     = true;
        $this->sending  = false;
        $this->flash    = "Notification queued for {$notification->total_recipients} recipients.";
        $this->reset(['title', 'message']);
    }

    public function render()
    {
        $this->count = $this->getRecipientsCount();
        $isAdmin = Auth::user()->hasAnyRole(['admin', 'super-admin', 'principal', 'deputy-principal', 'hod']);
        $classes = SchoolClass::forConfiguredGrades()->where('is_active', true);
        if (!$isAdmin) {
            $classIds = \App\Models\TeacherSubjectAllocation::where('teacher_id', Auth::user()->staffMember?->id)->where('is_active', true)->pluck('class_id');
            $classes->whereIn('id', $classIds);
        }
        return view('livewire.notifications.send-notification', ['classes' => $classes->orderBy('grade_level')->get(), 'isAdmin' => $isAdmin])
            ->layout($isAdmin ? 'layouts.admin' : 'layouts.teacher');
    }

    private function recipientQuery()
    {
        $query = Guardian::query();
        $isAdmin = Auth::user()->hasAnyRole(['admin', 'super-admin', 'principal', 'deputy-principal', 'hod']);
        if (!$isAdmin) {
            $classIds = \App\Models\TeacherSubjectAllocation::where('teacher_id', Auth::user()->staffMember?->id)->where('is_active', true)->pluck('class_id');
            $query->whereHas('learners', fn ($q) => $q->whereIn('class_id', $classIds)->where('is_active', true));
        }
        if ($this->targetClassId) $query->whereHas('learners', fn ($q) => $q->where('class_id', $this->targetClassId)->where('is_active', true));
        if ($this->targetGrade) $query->whereHas('learners', fn ($q) => $q->where('grade_level', $this->targetGrade)->where('is_active', true));
        if ($this->targetGroup === 'boarding') $query->whereHas('learners', fn ($q) => $q->where('boarding_status', 'boarding'));
        if ($this->targetGroup === 'day') $query->whereHas('learners', fn ($q) => $q->where('boarding_status', 'day'));
        return $query->whereNotNull('phone_number')->distinct();
    }
}
