<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SchoolDiagnosticReport extends Mailable
{
    use Queueable, SerializesModels;

    public $school;
    public $diagnosticData;

    public function __construct($school, array $diagnosticData)
    {
        $this->school = $school;
        $this->diagnosticData = $diagnosticData;
    }

    public function build()
    {
        return $this->subject('System Diagnostic & AI Health Summary - ' . $this->school->name)
                    ->markdown('emails.schools.diagnostic');
    }
}
