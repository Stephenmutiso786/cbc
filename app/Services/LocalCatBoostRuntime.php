<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class LocalCatBoostRuntime
{
    public function available(): bool
    {
        $process = new Process([$this->python(), '-c', 'import catboost']);
        $process->setTimeout(10);
        $process->run();
        return $process->isSuccessful();
    }

    public function run(string $operation, array $samples): array
    {
        if (! $this->available()) {
            throw new \RuntimeException('Local CatBoost is not installed. Install the bundled ml-service requirements on this server.');
        }
        $process = new Process([$this->python(), base_path('ml-service/cli.py')]);
        $process->setInput(json_encode(['operation' => $operation, 'samples' => $samples, 'model_path' => storage_path('app/ml/cbe-risk-model.cbm')], JSON_THROW_ON_ERROR));
        $process->setTimeout((int) config('services.risk_prediction.timeout', 120));
        $process->run();
        $data = json_decode($process->getOutput(), true);
        if (! $process->isSuccessful() || ! is_array($data) || empty($data['ok'])) {
            throw new \RuntimeException($data['error'] ?? trim($process->getErrorOutput()) ?: 'Local CatBoost operation failed.');
        }
        return $data;
    }

    private function python(): string { return (string) config('services.risk_prediction.python_binary', 'python3'); }
}
