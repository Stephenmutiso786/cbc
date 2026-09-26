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
class TrainRiskModel implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1; public int $timeout = 900;
    public function handle(RiskPredictionService $risk): void
    {
        // The shared model learns from anonymised historical measurements from
        // every active school. Feature access controls who can view predictions;
        // it must not silently discard a school's legitimate historical data.
        $samples = [];
        foreach (School::where('is_active', true)->get() as $school) {
            SchoolSettingsLoader::for($school->id);
            $samples = [...$samples, ...$risk->trainSamples($school)];
        }
        $risk->train($samples);
    }
}
