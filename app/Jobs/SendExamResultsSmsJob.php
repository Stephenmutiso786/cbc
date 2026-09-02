<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\NotificationLog;
use App\Models\SchoolNotification;
use App\Services\OlympusSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class SendExamResultsSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $examId, public readonly int $notificationId) {}

    public function handle(OlympusSmsService $sms): void
    {
        $exam = Exam::with(['learningArea', 'results.learner.guardians'])->findOrFail($this->examId);
        $notification = SchoolNotification::findOrFail($this->notificationId);
        $sent = 0;
        $failed = 0;

        foreach ($exam->results as $result) {
            $learner = $result->learner;
            if (! $learner) continue;

            foreach ($learner->guardians->whereNotNull('phone_number') as $guardian) {
                $message = $this->messageFor($exam, $result);
                try {
                    $providerResult = $sms->sendSms($guardian->phone_number, $message);
                    $sent++;
                    NotificationLog::create([
                        'notification_id' => $notification->id,
                        'recipient_phone' => $guardian->phone_number,
                        'channel' => 'sms',
                        'status' => 'sent',
                        'provider_message_id' => data_get($providerResult, 'data.uid') ?? data_get($providerResult, 'data.id'),
                        'sent_at' => now(),
                    ]);
                } catch (Throwable $exception) {
                    $failed++;
                    NotificationLog::create([
                        'notification_id' => $notification->id,
                        'recipient_phone' => $guardian->phone_number,
                        'channel' => 'sms',
                        'status' => 'failed',
                        'error_message' => $exception->getMessage(),
                    ]);
                    report($exception);
                }
            }
        }

        $status = $failed > 0 && $sent === 0 ? 'failed' : ($failed > 0 ? 'partial' : 'sent');
        $notification->update(['status' => $status, 'sent_count' => $sent, 'failed_count' => $failed, 'sent_at' => now()]);
        $exam->update(['results_sms_status' => $status, 'results_sms_sent_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        Exam::whereKey($this->examId)->update(['results_sms_status' => 'failed']);
        SchoolNotification::whereKey($this->notificationId)->update(['status' => 'failed']);
        report($exception);
    }

    private function messageFor(Exam $exam, $result): string
    {
        $learnerName = Str::limit($result->learner->full_name, 48, '');
        $examName = Str::limit($exam->typeLabel() . ' - ' . $exam->name, 42, '');
        $marks = rtrim(rtrim(number_format((float) $result->marks_obtained, 2, '.', ''), '0'), '.');
        $total = rtrim(rtrim(number_format((float) $result->total_marks, 2, '.', ''), '0'), '.');
        $message = "{$learnerName}: {$examName} {$marks}/{$total} ({$result->grade}). @KYANDULU SCHOOL";

        return Str::limit($message, 160, '');
    }
}
