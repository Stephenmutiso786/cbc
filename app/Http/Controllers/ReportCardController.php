<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Services\GoogleDriveStorage;
use App\Services\ReportCardService;
use Illuminate\Http\Response;

class ReportCardController extends Controller
{
    public function download(Learner $learner, ReportCardService $service, GoogleDriveStorage $storage): Response
    {
        abort_unless(auth()->user()->can('view report cards'), 403);

        $term = (string) config('school.current_term');
        $academicYear = (string) config('school.academic_year');
        $path = $service->generate($learner->id, $term, $academicYear);

        return response($storage->contents($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . addslashes($learner->admission_number . '-report-card.pdf') . '"',
        ]);
    }

    public function studentDownload(ReportCardService $service, GoogleDriveStorage $storage): Response
    {
        $learner = auth()->user()->learner;
        abort_unless($learner, 403, 'Your account is not linked to a learner record.');
        return $this->downloadFor($learner, $service, $storage);
    }

    public function parentDownload(Learner $learner, ReportCardService $service, GoogleDriveStorage $storage): Response
    {
        abort_unless(auth()->user()->guardian?->learners()->whereKey($learner->id)->exists(), 403);
        return $this->downloadFor($learner, $service, $storage);
    }

    private function downloadFor(Learner $learner, ReportCardService $service, GoogleDriveStorage $storage): Response
    {
        $term = (string) config('school.current_term');
        $academicYear = (string) config('school.academic_year');
        $path = $service->generate($learner->id, $term, $academicYear);
        return response($storage->contents($path), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . addslashes($learner->admission_number . '-report-card.pdf') . '"']);
    }
}
