<?php

namespace App\Mail;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SchoolRiskPredictionReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public School $school, public int $learnersAnalysed, public int $highRiskCount)
    {
    }

    public function build(): self
    {
        return $this->subject('New learner-support insights are ready - ' . $this->school->name)
            ->markdown('emails.schools.risk-prediction');
    }
}
