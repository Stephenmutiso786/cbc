<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class LearnerRiskPrediction extends Model
{
    use BelongsToSchool;
    protected $fillable = ['school_id', 'learner_id', 'risk_score', 'risk_level', 'features', 'top_factors', 'computed_at'];
    protected $casts = ['features' => 'array', 'top_factors' => 'array', 'computed_at' => 'datetime', 'risk_score' => 'float'];
    public function learner() { return $this->belongsTo(Learner::class); }
}
