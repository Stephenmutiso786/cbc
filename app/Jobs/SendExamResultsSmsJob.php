<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\NotificationLog;
use App\Models\SchoolNotification;
use App\Models\ExamResult;
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
        $exam = Exam::findOrFail($this->examId);
        $resultsByLearner = ExamResult::with(['learner.guardians', 'exam.learningArea'])
            ->whereIn('exam_id', $exam->groupExamIds())
            ->whereHas('learner')
            ->get()
            ->groupBy('learner_id');
        $notification = SchoolNotification::findOrFail($this->notificationId);
        $sent = 0;
        $failed = 0;

        foreach ($resultsByLearner as $learnerResults) {
            $learner = $learnerResults->first()->learner;
            if (! $learner) continue;

            foreach ($learner->guardians->whereNotNull('phone_number')->unique('id') as $guardian) {
                $message = $this->messageFor($exam, $learnerResults);
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

    private function messageFor(Exam $exam, $results): string
    {
        $learnerName = Str::limit($results->first()->learner->full_name, 34, '');
        $examName = Str::limit($exam->typeLabel() . ' - ' . $exam->name, 42, '');
        $classGrade = Str::limit((string) ($exam->grade_level ?: $results->first()->learner->grade_level?->value ?? $results->first()->learner->grade_level), 12, '');
        $subjects = $results->map(function ($result): string {
            $subject = Str::limit($result->exam?->learningArea?->name ?? 'Subject', 12, '');
            $rubric = $result->rubric_level?->value ?? '-';
            $points = $result->rubric_level?->numericValue() ?? '-';
            $grade = $result->grade ?: '-';
            return "{$subject}:{$rubric}/{$points}/{$grade}";
        })->implode(', ');
        $message = "{$learnerName}, {$classGrade}: {$examName} {$subjects}. @KYANDULU SCHOOL";

        return Str::limit($message, 160, '');
    }
}
