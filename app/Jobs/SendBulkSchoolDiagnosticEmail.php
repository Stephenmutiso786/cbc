<?php

namespace App\Jobs;

use App\Mail\SchoolDiagnosticReport;
use App\Services\SchoolDiagnosticService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBulkSchoolDiagnosticEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $school;
    protected $ticketPayload;

    public function __construct($school, array $ticketPayload)
    {
        $this->school = $school;
        $this->ticketPayload = $ticketPayload;
    }

    public function handle(SchoolDiagnosticService $diagnostics)
    {
        $diagnosticData = $diagnostics->assess($this->ticketPayload);

        if (!empty($this->school->email)) {
            Mail::to($this->school->email)->send(new SchoolDiagnosticReport($this->school, $diagnosticData));
        }
    }
}
