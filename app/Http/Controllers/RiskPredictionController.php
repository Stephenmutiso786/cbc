<?php
namespace App\Http\Controllers;
use App\Jobs\RecomputeRiskPredictions;
use App\Jobs\TrainRiskModel;
use App\Models\LearnerRiskPrediction;
use App\Models\School;
use App\Services\RiskPredictionService;
use Illuminate\Http\Request;

class RiskPredictionController extends Controller {
    public function index(Request $request) { $query = LearnerRiskPrediction::with(['learner.schoolClass'])->orderByDesc('risk_score'); if ($request->filled('class')) $query->whereHas('learner', fn($q) => $q->where('class_id', $request->class)); return view('admin.risk.index', ['predictions' => $query->paginate(25), 'classes' => auth()->user()->school?->classes()->orderBy('name')->get() ?? collect()]); }
    public function platform(RiskPredictionService $risk) { $schools = School::with('package')->withCount(['learners', 'learners as high_risk_count' => fn($q) => $q->whereHas('riskPrediction', fn($r) => $r->where('risk_level', 'high'))])->get(); return view('admin.risk.platform', compact('schools', 'risk')); }
    public function recompute() { RecomputeRiskPredictions::dispatch(); return back()->with('success', 'Risk prediction run queued.'); }
    public function train() { TrainRiskModel::dispatch(); return back()->with('success', 'CatBoost model training queued.'); }
    public function show(LearnerRiskPrediction $prediction) { abort_unless(auth()->user()->hasRole('super-admin') || $prediction->school_id === auth()->user()->school_id, 403); return view('admin.risk.show', compact('prediction')); }
}
