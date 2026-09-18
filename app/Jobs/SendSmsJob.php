<?php

namespace App\Jobs;

use App\Models\Guardian;
use App\Models\NotificationLog;
use App\Models\School;
use App\Models\SchoolNotification;
use App\Services\OlympusSmsService;
use App\Support\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int    $notificationId,
        public readonly string $targetGrade = '',
        public readonly string $targetGroup = 'all',
        public readonly ?int   $targetClassId = null,
    ) {}

    public function handle(OlympusSmsService $sms): void
    {
        // A queue worker has no authenticated user, so without this the
        // Guardian lookup below would run unscoped and could message
        // guardians belonging to other schools. Scope explicitly to the
        // notification's own school for the whole job.
        $notification = SchoolNotification::withoutSchoolScope()->findOrFail($this->notificationId);

        Tenant::run($notification->school_id, function () use ($notification, $sms) {
            // Queue workers don't go through the web middleware that loads
            // per-school branding — load it explicitly (SMS credentials
            // themselves are platform-wide now, loaded in AppServiceProvider).
            \App\Support\SchoolSettingsLoader::for($notification->school_id);

            $school = School::findOrFail($notification->school_id);
            $notification->update(['status' => 'queued', 'sent_at' => now()]);

            // Build recipients query
            $query = Guardian::query()->whereNotNull('phone_number');

            if ($this->targetGrade) {
                $query->whereHas('learners', fn($q) => $q->where('grade_level', $this->targetGrade)->where('is_active', true));
            }
            if ($this->targetClassId) {
                $query->whereHas('learners', fn($q) => $q->where('class_id', $this->targetClassId)->where('is_active', true));
            }
            if ($this->targetGroup === 'boarding') {
                $query->whereHas('learners', fn($q) => $q->where('boarding_status', 'boarding'));
            } elseif ($this->targetGroup === 'day') {
                $query->whereHas('learners', fn($q) => $q->where('boarding_status', 'day'));
            }

            $guardians   = $query->distinct()->get(['id', 'phone_number']);
            $sent        = 0;
            $failed      = 0;
            $school      = \App\Models\School::find($notification->school_id);
            $message     = "{$notification->title}\n\n{$notification->message}\n\nRegards, " . ($school?->name ?? 'the school') . ".";
            // Credit usage must match the actual composed message, including
            // the school signature. Long SMS messages are split by gateways.
            $unitsPerRecipient = max(1, (int) ceil(mb_strlen($message) / 153));

            // Send in batches of 50, drawing down the school's SMS wallet
            // one batch at a time so a mid-run top-up can still be used.
            foreach ($guardians->chunk(50) as $batch) {
                $phones = $batch->pluck('phone_number')->toArray();

                $batchUnits = count($phones) * $unitsPerRecipient;
                if (! $school->deductSmsCredits($batchUnits, "Notification #{$notification->id}: {$notification->title}")) {
                    $failed += count($phones);
                    foreach ($batch as $guardian) {
                        \DB::table('notification_logs')->insert([
                            'school_id'       => $notification->school_id,
                            'notification_id' => $notification->id,
                            'recipient_phone' => $guardian->phone_number,
                            'channel'         => 'sms',
                            'status'          => 'failed',
                            'error_message'   => 'Insufficient SMS credits. Contact your platform administrator to top up.',
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                    }
                    continue;
                }

                try {
                    $result = $sms->sendSms($phones, $message);
                    $sent  += count($phones);

                    foreach ($batch as $guardian) {
                        \DB::table('notification_logs')->insert([
                            'school_id'         => $notification->school_id,
                            'notification_id'   => $notification->id,
                            'recipient_phone'   => $guardian->phone_number,
                            'channel'           => 'sms',
                            'status'            => 'sent',
                            'sent_at'           => now(),
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    // The provider rejected the batch after credits were
                    // already deducted — refund them.
                    $school->addSmsCredits($batchUnits, 'adjustment', null, 'Refund: provider failed batch for notification #' . $notification->id);
                    $failed += count($phones);
                    Log::error('SMS batch failed', ['error' => $e->getMessage(), 'phones' => $phones]);
                }
            }

            $notification->update([
                'status'          => $failed > 0 && $sent === 0 ? 'failed' : ($failed > 0 ? 'partial' : 'sent'),
                'sent_count'      => $sent,
                'failed_count'    => $failed,
                'total_recipients'=> $guardians->count(),
            ]);
        });
    }
}
