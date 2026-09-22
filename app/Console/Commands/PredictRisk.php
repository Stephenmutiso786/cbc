<?php
namespace App\Console\Commands;
use App\Jobs\RecomputeRiskPredictions;
use App\Services\RiskPredictionService;
use Illuminate\Console\Command;
class PredictRisk extends Command {
    protected $signature = 'risk:predict {--sync : Run now instead of queueing}'; protected $description = 'Compute learner risk predictions for eligible schools.';
    public function handle(RiskPredictionService $risk): int { if (! $risk->configured()) { $this->error('ML service URL/API key is not configured.'); return self::FAILURE; } if ($this->option('sync')) (new RecomputeRiskPredictions)->handle($risk); else RecomputeRiskPredictions::dispatch(); $this->info($this->option('sync') ? 'Predictions computed.' : 'Prediction run queued.'); return self::SUCCESS; }
}
