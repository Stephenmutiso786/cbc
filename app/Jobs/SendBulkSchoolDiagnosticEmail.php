<?php

namespace App\Jobs;

use App\Mail\SchoolDiagnosticReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
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

    public function handle()
    {
        $catboostUrl = config('services.catboost.url', 'http://127.0.0.1:8000/api/ml/predict-ticket');

        try {
            $response = Http::post($catboostUrl, $this->ticketPayload);
            $diagnosticData = $response->json();
        } catch (\Exception $e) {
            $diagnosticData = [
                'prediction' => 'Service Unavailable',
                'confidence' => 0,
                'recommended_action' => 'Manual review required.'
            ];
        }

        if (!empty($this->school->email)) {
            Mail::to($this->school->email)->send(new SchoolDiagnosticReport($this->school, $diagnosticData));
        }
    }
}
