<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ExamResult;
use App\Models\FeeInvoice;
use App\Models\Learner;
use App\Models\LearnerRiskPrediction;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Str;

class RiskPredictionService
{
    public const FEATURE_KEYS = ['attendance_30d', 'attendance_term', 'recent_score', 'term_score', 'score_trend', 'fee_balance_ratio', 'days_overdue', 'terms_enrolled', 'missed_assessment_ratio'];

    public function configured(): bool { return app(LocalCatBoostRuntime::class)->available(); }

    /** Numeric-only vector. $asOf makes historical training labels non-circular. */
    public function features(Learner $learner, Carbon $asOf): array
    {
        $attendance = Attendance::withoutSchoolScope()->where('learner_id', $learner->id)->whereDate('date', '<=', $asOf);
        $attendanceRate = fn (Carbon $from) => (clone $attendance)->whereDate('date', '>=', $from)->selectRaw("AVG(CASE WHEN status IN ('present','late') THEN 1.0 ELSE 0 END) as rate")->value('rate');
        $scores = ExamResult::withoutSchoolScope()->where('learner_id', $learner->id)->whereHas('exam', fn ($q) => $q->whereDate('exam_date', '<=', $asOf));
        $score = fn (Carbon $from) => (clone $scores)->whereHas('exam', fn ($q) => $q->whereDate('exam_date', '>=', $from))->selectRaw('AVG(CASE WHEN total_marks > 0 THEN marks_obtained * 100.0 / total_marks END) as score')->value('score');
        $recent = (float) ($score($asOf->copy()->subDays(30)) ?? 0);
        $term = (float) ($score($asOf->copy()->subDays(90)) ?? 0);
        $invoices = FeeInvoice::withoutSchoolScope()->where('learner_id', $learner->id)->whereDate('created_at', '<=', $asOf);
        $total = (float) (clone $invoices)->sum('total_amount');
        $balance = (float) (clone $invoices)->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as balance')->value('balance');
        $overdue = (clone $invoices)->where('due_date', '<', $asOf)->whereColumn('amount_paid', '<', 'total_amount')->min('due_date');
        $assessmentCount = (clone $scores)->count();
        $expected = max(1, (int) ceil(max(0, Carbon::parse($learner->admission_date)->diffInDays($asOf, false)) / 30));
        return [
            round((float) ($attendanceRate($asOf->copy()->subDays(30)) ?? 1), 4), round((float) ($attendanceRate($asOf->copy()->subDays(90)) ?? 1), 4),
            round($recent, 2), round($term, 2), round($recent - $term, 2), round($total > 0 ? min(1, $balance / $total) : 0, 4),
            $overdue ? min(365, Carbon::parse($overdue)->diffInDays($asOf)) : 0, min(40, $expected / 3), round(max(0, 1 - min(1, $assessmentCount / $expected)), 4),
        ];
    }

    public function trainSamples(School $school): array
    {
        /*
         * Each run rebuilds the training set from several matured snapshots,
         * rather than treating one old day as the entire school's history.
         * The most recent 30 days remain prediction-only: their outcome is
         * not known yet, so using them as labels would falsely train on the
         * future. On later runs those records mature and are added here.
         */
        $samples = [];
        foreach (collect(range(2, 8))->map(fn (int $months) => now()->subMonths($months)->startOfDay()) as $asOf) {
            $outcomeEnd = $asOf->copy()->addDays(30)->endOfDay();
            Learner::withoutSchoolScope()
                ->where('school_id', $school->id)
                ->whereDate('admission_date', '<=', $asOf)
                ->get()
                ->each(function (Learner $learner) use (&$samples, $school, $asOf, $outcomeEnd): void {
                    $futureScores = ExamResult::withoutSchoolScope()
                        ->where('learner_id', $learner->id)
                        ->whereHas('exam', fn ($q) => $q->whereBetween('exam_date', [$asOf, $outcomeEnd]))
                        ->selectRaw('AVG(CASE WHEN total_marks > 0 THEN marks_obtained * 100.0 / total_marks END) as score')
                        ->value('score');
                    $futureAttendance = Attendance::withoutSchoolScope()
                        ->where('learner_id', $learner->id)->whereBetween('date', [$asOf, $outcomeEnd])
                        ->selectRaw("AVG(CASE WHEN status IN ('present','late') THEN 1.0 ELSE 0 END) as rate")
                        ->value('rate');
                    $overdue = FeeInvoice::withoutSchoolScope()
                        ->where('learner_id', $learner->id)->whereDate('due_date', '<=', $outcomeEnd)
                        ->whereColumn('amount_paid', '<', 'total_amount')->exists();

                    // No observed outcome means this snapshot cannot teach the model.
                    if ($futureScores === null && $futureAttendance === null && ! $overdue) {
                        return;
                    }

                    $label = (($futureScores !== null && $futureScores < 45)
                        || ($futureAttendance !== null && $futureAttendance < .70)
                        || $overdue || ! $learner->is_active) ? 1 : 0;
                    $samples[] = [
                        'reference_id' => hash('sha256', 'train:' . $school->id . ':' . $learner->id . ':' . $asOf->toDateString()),
                        'features' => $this->features($learner, $asOf),
                        'label' => (int) $label,
                    ];
                });
        }

        return $samples;
    }

    public function train(array $samples): array
    {
        if (count($samples) < 30) throw new \RuntimeException('Training needs at least 30 learners with 90+ days of history.');
        return $this->request('train', ['samples' => $samples]);
    }
    public function predictSchool(School $school): int
    {
        if (! $school->hasFeature('predictive_analytics')) return 0;
        $learners = Learner::withoutSchoolScope()->where('school_id', $school->id)->where('is_active', true)->get();
        if ($learners->isEmpty()) return 0;
        $map = [];
        $samples = $learners->map(function (Learner $learner) use (&$map) { $ref = Str::uuid()->toString(); $map[$ref] = $learner; return ['reference_id' => $ref, 'features' => $this->features($learner, now())]; })->all();
        $response = $this->request('predict', ['samples' => $samples]);
        foreach ($response['predictions'] ?? [] as $result) {
            if (! isset($map[$result['reference_id']])) continue;
            $learner = $map[$result['reference_id']];
            $values = $this->features($learner, now());
            LearnerRiskPrediction::withoutSchoolScope()->updateOrCreate(['school_id' => $school->id, 'learner_id' => $learner->id], ['risk_score' => $result['risk_score'], 'risk_level' => $result['risk_level'], 'features' => array_combine(self::FEATURE_KEYS, $values), 'top_factors' => $result['top_factors'] ?? [], 'computed_at' => now()]);
        }
        return count($response['predictions'] ?? []);
    }
    public function health(): array { return ['status' => $this->configured() ? 'local runtime ready' : 'local CatBoost is not installed', 'model_ready' => file_exists(storage_path('app/ml/cbe-risk-model.cbm'))]; }
    private function request(string $path, array $payload): array { return app(LocalCatBoostRuntime::class)->run($path, $payload['samples'] ?? []); }
}
