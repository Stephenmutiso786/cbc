<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ExamResult;
use App\Models\FeeInvoice;
use App\Models\Learner;
use App\Models\LearnerRiskPrediction;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RiskPredictionService
{
    public const FEATURE_KEYS = ['attendance_30d', 'attendance_term', 'recent_score', 'term_score', 'score_trend', 'fee_balance_ratio', 'days_overdue', 'terms_enrolled', 'missed_assessment_ratio'];

    public function configured(): bool { return filled(config('services.risk_prediction.url')) && filled(config('services.risk_prediction.api_key')); }

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
        $cutoff = now()->subDays(90)->startOfDay();
        return Learner::withoutSchoolScope()->where('school_id', $school->id)->whereDate('admission_date', '<=', $cutoff->copy()->subDays(90))->get()->map(function (Learner $learner) use ($cutoff) {
            $features = $this->features($learner, $cutoff);
            $futureScores = ExamResult::withoutSchoolScope()->where('learner_id', $learner->id)->whereHas('exam', fn ($q) => $q->whereDate('exam_date', '>', $cutoff))->selectRaw('AVG(CASE WHEN total_marks > 0 THEN marks_obtained * 100.0 / total_marks END) as score')->value('score');
            $futureAttendance = Attendance::withoutSchoolScope()->where('learner_id', $learner->id)->whereDate('date', '>', $cutoff)->selectRaw("AVG(CASE WHEN status IN ('present','late') THEN 1.0 ELSE 0 END) as rate")->value('rate');
            $overdue = FeeInvoice::withoutSchoolScope()->where('learner_id', $learner->id)->whereDate('due_date', '<=', now()->subDays(30))->whereColumn('amount_paid', '<', 'total_amount')->exists();
            $label = (($futureScores !== null && $futureScores < 45) || ($futureAttendance !== null && $futureAttendance < .70) || $overdue || ! $learner->is_active) ? 1 : 0;
            return ['reference_id' => hash('sha256', 'train:' . $school->id . ':' . $learner->id), 'features' => $features, 'label' => (int) $label];
        })->all();
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
    public function health(): array { try { return $this->configured() ? Http::timeout(config('services.risk_prediction.timeout'))->get(rtrim(config('services.risk_prediction.url'), '/') . '/health')->throw()->json() : ['status' => 'not configured']; } catch (\Throwable) { return ['status' => 'unavailable']; } }
    private function request(string $path, array $payload): array { return Http::timeout(config('services.risk_prediction.timeout'))->withHeaders(['X-API-Key' => config('services.risk_prediction.api_key')])->post(rtrim(config('services.risk_prediction.url'), '/') . '/' . $path, $payload)->throw()->json(); }
}
