<?php
namespace App\Jobs;
use App\Models\School;
use App\Services\RiskPredictionService;
use App\Support\SchoolSettingsLoader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class RecomputeRiskPredictions implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 2; public int $timeout = 900;
    public function handle(RiskPredictionService $risk): void { foreach (School::with('package')->get() as $school) if ($school->hasFeature('predictive_analytics')) { SchoolSettingsLoader::for($school->id); $risk->predictSchool($school); } }
}
