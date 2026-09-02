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
        $exam = Exam::with('schoolClass')->findOrFail($this->examId);
        $scale = $exam->schoolClass?->gradingScale()->first();
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

            foreach ($learner->guardians->filter(fn ($guardian) => trim((string) $guardian->phone_number) !== '')->unique('id') as $guardian) {
                $message = $this->messageFor($exam, $learnerResults, $scale);
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

    private function messageFor(Exam $exam, $results, $scale): string
    {
        $learner = $results->first()->learner;
        $learnerName = Str::limit($learner->full_name, 42, '');
        $admission = Str::limit((string) ($learner->admission_number ?: '-'), 18, '');
        $examName = Str::limit($exam->typeLabel() . ' - ' . $exam->name, 48, '');
        $term = 'Term ' . (int) $exam->term;
        $subjects = $results->map(function ($result) use ($scale): string {
            $grade = $this->gradeForResult($result, $scale);
            return $this->subjectLabel($result->exam?->learningArea?->name) . '-' . $grade;
        })->implode(', ');
        $totalPoints = $results->sum(fn ($result): int => $this->pointsForResult($result, $scale));
        $possible = $results->sum(fn ($result): float => (float) $result->total_marks);
        $obtained = $results->sum(fn ($result): float => (float) ($result->marks_obtained ?? 0));
        $percentage = $possible > 0 ? ($obtained / $possible) * 100 : 0;
        $meanGrade = $this->overallGrade($percentage, $scale);
        $remark = $this->remarkFor($percentage);
        $schoolName = Str::limit((string) config('school.name', 'Kyandulu School'), 45, '');

        $message = "Dear Parent/Guardian,\n"
            . "Here is the {$term} {$examName} summary for {$learnerName}, Adm: {$admission}.\n"
            . "Mean Grade: {$meanGrade}\n"
            . "Subjects: {$subjects}\n"
            . "Total Points: {$totalPoints}\n"
            . "Remarks: {$remark}\n"
            . "Regards, {$schoolName}. @KYANDULU SCHOOL";

        if (strlen($message) <= 320) {
            return $message;
        }

        // Preserve every subject and the footer if unusually long names exceed two SMS segments.
        $compact = "Dear Parent/Guardian, {$term} {$examName}: {$learnerName}, Adm {$admission}. "
            . "Mean Grade: {$meanGrade}. Subjects: {$subjects}. Total Points: {$totalPoints}. "
            . "Remarks: {$remark}. Regards, {$schoolName}. @KYANDULU SCHOOL";

        return $compact;
    }

    private function subjectLabel(?string $name): string
    {
        return match (strtolower(trim((string) $name))) {
            'mathematics' => 'Maths',
            'kiswahili' => 'Kiswahili',
            'integrated science' => 'Int Sci',
            'christian religious education (cre)', 'christian religious education' => 'CRE',
            'agriculture and nutrition' => 'Agric/Nut',
            'creative arts and sports' => 'Arts/Sports',
            'pre-technical studies' => 'Pre-Tech',
            'social studies' => 'Social',
            'english' => 'English',
            default => Str::limit(trim((string) $name) ?: 'Subject', 8, ''),
        };
    }

    private function gradeForResult($result, $scale): string
    {
        if ($result->grade) {
            return (string) $result->grade;
        }

        if ($result->marks_obtained !== null && (float) $result->total_marks > 0 && $scale) {
            return $scale->gradeForPercent(((float) $result->marks_obtained / (float) $result->total_marks) * 100) ?: '-';
        }

        return $result->rubric_level?->value ?? '-';
    }

    private function pointsForResult($result, $scale): int
    {
        $percentage = $result->marks_obtained !== null && (float) $result->total_marks > 0
            ? ((float) $result->marks_obtained / (float) $result->total_marks) * 100
            : null;

        if ($percentage !== null && $scale) {
            $grade = $scale->gradeForPercent($percentage);
            foreach ($scale->bands ?? [] as $band) {
                if (($band['code'] ?? null) === $grade && isset($band['points'])) {
                    return (int) $band['points'];
                }
            }
        }

        return $result->rubric_level?->numericValue() ?? match (substr($this->gradeForResult($result, $scale), 0, 2)) {
            'EE' => 4,
            'ME' => 3,
            'AE' => 2,
            default => 1,
        };
    }

    private function overallGrade(float $percentage, $scale): string
    {
        if ($scale && ($grade = $scale->gradeForPercent($percentage)) !== null) {
            return $grade;
        }

        return match (true) {
            $percentage >= 80 => 'A',
            $percentage >= 70 => 'B',
            $percentage >= 60 => 'C',
            $percentage >= 50 => 'D',
            default => 'E',
        };
    }

    private function remarkFor(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'Excellent work, keep it up.',
            $percentage >= 75 => 'Very good effort, keep improving.',
            $percentage >= 50 => 'Good effort, keep improving.',
            default => 'Keep working consistently and seek support.',
        };
    }
}
