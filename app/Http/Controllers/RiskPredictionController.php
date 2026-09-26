<?php
namespace App\Http\Controllers;
use App\Jobs\RecomputeRiskPredictions;
use App\Jobs\TrainRiskModel;
use App\Mail\SchoolRiskPredictionReport;
use App\Mail\LearnerRiskReview;
use App\Models\LearnerRiskPrediction;
use App\Models\PlatformBroadcast;
use App\Models\School;
use App\Models\User;
use App\Services\RiskPredictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class RiskPredictionController extends Controller {
    public function index(Request $request) { $query = LearnerRiskPrediction::with(['learner.schoolClass'])->orderByDesc('risk_score'); if ($request->filled('class')) $query->whereHas('learner', fn($q) => $q->where('class_id', $request->class)); return view('admin.risk.index', ['predictions' => $query->paginate(25), 'classes' => auth()->user()->school?->classes()->orderBy('name')->get() ?? collect()]); }
    public function platform(RiskPredictionService $risk) {
        $schools = School::with('package')->withCount(['learners', 'learners as high_risk_count' => fn($q) => $q->whereHas('riskPrediction', fn($r) => $r->where('risk_level', 'high'))])->get();
        $trainingReadiness = $schools->map(function (School $school) use ($risk): array {
            $samples = $school->is_active ? $risk->trainSamples($school) : [];
            return [
                'school' => $school,
                'eligible' => $school->hasFeature('predictive_analytics'),
                'samples' => count($samples),
                'risk_examples' => collect($samples)->where('label', 1)->count(),
                'stable_examples' => collect($samples)->where('label', 0)->count(),
                'predictions' => LearnerRiskPrediction::withoutSchoolScope()->where('school_id', $school->id)->count(),
            ];
        });
        return view('admin.risk.platform', compact('schools', 'risk', 'trainingReadiness'));
    }
    public function recompute(RiskPredictionService $risk) {
        try {
            (new RecomputeRiskPredictions)->handle($risk);
            return back()->with('success', 'Learner-support predictions were computed for every eligible school.');
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'Predictions could not be computed: ' . $exception->getMessage());
        }
    }
    public function train(RiskPredictionService $risk) {
        try {
            (new TrainRiskModel)->handle($risk);
            return back()->with('success', 'The local CatBoost model was trained successfully. Run “Compute school results” next.');
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'CatBoost training could not finish: ' . $exception->getMessage());
        }
    }
    public function emailSchools(): \Illuminate\Http\RedirectResponse
    {
        $sent = 0;
        $failed = 0;
        foreach (School::with('package')->where('is_active', true)->get() as $school) {
            if (! $school->hasFeature('predictive_analytics') || ! $school->email) {
                continue;
            }
            $predictions = LearnerRiskPrediction::withoutSchoolScope()->with(['learner.schoolClass'])->where('school_id', $school->id);
            $analysed = (clone $predictions)->count();
            if ($analysed === 0) {
                continue;
            }
            try {
                $recipients = User::withoutSchoolScope()->where('school_id', $school->id)->where('status', 'active')->get()
                    ->filter(fn (User $user) => $user->hasAnyRole(['school-admin', 'headteacher', 'principal', 'deputy-headteacher', 'deputy']))
                    ->pluck('email')->filter()->unique()->values();
                if ($recipients->isEmpty() && $school->email) {
                    $recipients = collect([$school->email]);
                }
                if ($recipients->isEmpty()) {
                    continue;
                }

                // Generate one actionable review email per learner requiring attention.
                (clone $predictions)->whereIn('risk_level', ['high', 'medium'])->orderByDesc('risk_score')->each(function (LearnerRiskPrediction $prediction) use ($school, $recipients, &$sent): void {
                    Mail::to($recipients->all())->send(new LearnerRiskReview($school, $prediction));
                    $sent++;
                });

                // A summary remains useful when a school has no elevated learners.
                if ((clone $predictions)->whereIn('risk_level', ['high', 'medium'])->doesntExist()) {
                    Mail::to($recipients->all())->send(new SchoolRiskPredictionReport($school, $analysed, 0));
                    $sent++;
                }
            } catch (\Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        if ($sent > 0) {
            PlatformBroadcast::create([
                'title' => 'New learner-support insights are ready',
                'message' => 'Authorised school staff can now review the latest learner-support insights in At-Risk Learners.',
                'status' => 'sent', 'created_by' => auth()->id(), 'total_schools' => $sent,
                'schools_notified' => $sent, 'sent_at' => now(),
            ]);
        }

        return back()->with('success', "Generated learner-support emails sent: {$sent}. Failed schools: {$failed}. Each message is delivered only to that school's active administrators.");
    }
    public function show(LearnerRiskPrediction $prediction) { abort_unless(auth()->user()->hasRole('super-admin') || $prediction->school_id === auth()->user()->school_id, 403); return view('admin.risk.show', compact('prediction')); }
}
