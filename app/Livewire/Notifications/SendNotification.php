<?php

namespace App\Livewire\Notifications;

use App\Jobs\SendSmsJob;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolNotification;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SendNotification extends Component
{
    /**
     * Livewire actions are posted to the internal livewire.update endpoint.
     * Preserve the page context captured on mount so a school administrator
     * remains an administrator while selecting recipients or sending.
     */
    public bool $isAdminPortal = false;
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
        // Email and push delivery are not implemented by this sender. Do
        // not let the interface claim it sent a channel that it cannot send.
        'channel' => 'required|in:sms',
        'type'    => 'required|in:general,fees,exam,report_card,attendance,emergency',
    ];

    public function getRecipientsCount(): int
    {
        return $this->recipientQuery()->count();
    }

    public function updatedTargetGrade(): void  { $this->count = $this->getRecipientsCount(); }
    public function updatedTargetGroup(): void  { $this->count = $this->getRecipientsCount(); }
    public function updatedTargetClassId(): void { $this->count = $this->getRecipientsCount(); }

    public function mount(): void
    {
        $this->isAdminPortal = request()->routeIs('admin.*');
    }

    public function send(): void
    {
        abort_unless(Auth::user()->can('send notifications'), 403);
        $this->validate();
        $this->sending = true;

        $staff = Auth::user()->staffMember;
        if (!$staff) {
            $this->addError('message', 'This account is not linked to a staff profile.');
            $this->sending = false;
            return;
        }

        if (!$this->isAdminPortal && !$this->targetClassId) {
            $this->addError('targetClassId', 'Select the class whose parents should receive this message.');
            $this->sending = false;
            return;
        }
        if (!$this->isAdminPortal && !\App\Models\TeacherSubjectAllocation::where('teacher_id', $staff->id)->where('class_id', $this->targetClassId)->where('is_active', true)->exists()) {
            abort(403, 'You are not allocated to this class.');
        }

        if (! config('services.olympus_sms.api_token')) {
            $this->addError('message', 'SMS sending is not configured by the platform administrator yet. Ask them to add the provider token in Global Platform Settings and send a test SMS.');
            $this->sending = false;
            return;
        }

        $recipientCount = $this->getRecipientsCount();
        if ($recipientCount === 0) {
            $this->addError('targetClassId', 'No guardian phone numbers match this audience. Add guardian phone numbers before sending an SMS.');
            $this->sending = false;
            return;
        }

        $school = School::withoutGlobalScopes()->find(Auth::user()->school_id);
        if (! $school) {
            $this->addError('message', 'This account is not linked to a school SMS wallet.');
            $this->sending = false;
            return;
        }
        $fullMessage = "{$this->title}\n\n{$this->message}\n\nRegards, {$school->name}.";
        $requiredUnits = $recipientCount * max(1, (int) ceil(mb_strlen($fullMessage) / 153));
        if ((int) $school->sms_credits < $requiredUnits) {
            $this->addError('message', "Insufficient SMS credits. This message needs {$requiredUnits} credit(s); this school has {$school->sms_credits}.");
            $this->sending = false;
            return;
        }

        $notification = SchoolNotification::create([
            'sender_id'         => $staff->id,
            'title'             => $this->title,
            'message'           => $this->message,
            'type'              => $this->type,
            'channel'           => $this->channel,
            'target_grade'      => $this->targetGrade ?: null,
            'target_group'      => $this->targetGroup,
            'target_class_id'   => $this->targetClassId,
            'total_recipients'  => $recipientCount,
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
        $isAdmin = $this->isAdminPortal;
        $classes = SchoolClass::forConfiguredGrades()->where('is_active', true);
        if (!$isAdmin) {
            $classIds = \App\Models\TeacherSubjectAllocation::where('teacher_id', Auth::user()->staffMember?->id)->where('is_active', true)->pluck('class_id');
            $classes->whereIn('id', $classIds);
        }
        $view = view('livewire.notifications.send-notification', ['classes' => $classes->orderBy('grade_level')->get(), 'isAdmin' => $isAdmin]);
        return request()->routeIs('admin.sms.*')
            ? $view
            : $view->layout($isAdmin ? 'layouts.admin' : 'layouts.teacher');
    }

    private function recipientQuery()
    {
        $query = Guardian::query();
        $isAdmin = $this->isAdminPortal;
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
