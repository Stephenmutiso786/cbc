<?php
namespace App\Console\Commands;
use App\Jobs\TrainRiskModel as TrainJob;
use App\Services\RiskPredictionService;
use Illuminate\Console\Command;
class TrainRiskModel extends Command {
    protected $signature = 'risk:train-model {--sync : Run now instead of queueing}'; protected $description = 'Train the privacy-preserving CatBoost learner-risk model.';
    public function handle(RiskPredictionService $risk): int { if (! $risk->configured()) { $this->error('Local CatBoost runtime is not installed. Install ml-service/requirements.txt on this server.'); return self::FAILURE; } if ($this->option('sync')) (new TrainJob)->handle($risk); else TrainJob::dispatch(); $this->info($this->option('sync') ? 'Training completed.' : 'Training queued.'); return self::SUCCESS; }
}
