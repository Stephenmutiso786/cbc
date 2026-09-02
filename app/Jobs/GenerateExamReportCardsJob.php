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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
            $folder = 'reports/' . $exam->academic_year . '/term' . $exam->term . '/exams';
            $filename = 'exam-' . $exam->id . '-report-cards.pdf';
            try {
                $path = $storage->store($pdf, $folder, $filename, 'application/pdf');
            } catch (Throwable $storageException) {
                // A Drive outage or invalid optional credentials must not
                // turn a valid report into a failed export.
                Log::warning('Google Drive report upload failed; using local storage.', [
                    'exam_id' => $exam->id,
                    'message' => $storageException->getMessage(),
                ]);
                $path = $folder . '/' . $filename;
                Storage::disk('public')->put($path, $pdf);
            }

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
