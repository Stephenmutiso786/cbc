<?php

namespace App\Jobs;

use App\Http\Controllers\ExamReportsController;
use App\Models\Exam;
use App\Models\ExamReportExport;
use App\Services\GoogleDriveStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateExamReportCardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public readonly int $exportId) {}

    public function handle(ExamReportsController $reports, GoogleDriveStorage $storage): void
    {
        $export = ExamReportExport::findOrFail($this->exportId);
        $export->update(['status' => 'processing', 'error' => null]);

        try {
            $exam = Exam::findOrFail($export->exam_id);
            $pdf = $reports->buildResultCardsPdf($exam);
            $path = $storage->store(
                $pdf,
                'reports/' . $exam->academic_year . '/term' . $exam->term . '/exams',
                'exam-' . $exam->id . '-report-cards.pdf',
                'application/pdf'
            );

            $export->update(['status' => 'complete', 'path' => $path, 'finished_at' => now()]);
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
            report($exception);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        ExamReportExport::whereKey($this->exportId)->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
