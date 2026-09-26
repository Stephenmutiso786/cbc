<?php

namespace App\Mail;

use App\Models\LearnerRiskPrediction;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LearnerRiskReview extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public School $school, public LearnerRiskPrediction $prediction)
    {
    }

    public function build(): self
    {
        return $this->subject('Learner-support review required: ' . $this->prediction->learner?->full_name)
            ->markdown('emails.schools.learner-risk-review');
    }
}
