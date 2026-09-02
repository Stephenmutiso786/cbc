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
        abort_unless(auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal', 'deputy-principal', 'deputy']), 403);

        $term = (string) config('school.current_term');
        $academicYear = (string) config('school.academic_year');
        $path = $service->generate($learner->id, $term, $academicYear);

        return response($storage->contents($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . addslashes($learner->admission_number . '-report-card.pdf') . '"',
        ]);
    }
}
