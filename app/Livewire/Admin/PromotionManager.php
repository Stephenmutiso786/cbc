<?php

namespace App\Livewire\Admin;

use App\Models\Learner;
use App\Models\LearnerPromotion;
use App\Models\PromotionRule;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PromotionManager extends Component
{
    public string $sourceClassId = '';
    public string $targetClassId = '';
    public string $toYear = '';
    public string $ruleId = '';
    public array $selectedLearners = [];
    public bool $showRuleForm = false;
    public array $ruleForm = ['name' => '', 'from_grade' => '', 'to_grade' => '', 'minimum_average' => ''];
    public string $notice = '';

    public function mount(): void
    {
        $this->toYear = (string) ((int) config('school.academic_year') + 1);
    }

    public function createRequests(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'sourceClassId' => ['required', 'exists:school_classes,id'],
            'targetClassId' => ['required', 'different:sourceClassId', 'exists:school_classes,id'],
            'toYear' => ['required', 'integer', 'min:2000', 'max:2200'],
            'ruleId' => ['nullable', 'exists:promotion_rules,id'],
            'selectedLearners' => ['required', 'array', 'min:1'],
        ]);

        $source = SchoolClass::findOrFail($this->sourceClassId);
        $target = SchoolClass::findOrFail($this->targetClassId);
        abort_if($source->grade_level === $target->grade_level, 422, 'Choose the next grade class.');
        $rule = $this->ruleId ? PromotionRule::findOrFail($this->ruleId) : null;
        if ($rule && ($rule->from_grade !== $source->grade_level || $rule->to_grade !== $target->grade_level)) {
            $this->addError('ruleId', 'The selected rule does not match the source and target grades.');
            return;
        }

        $learners = Learner::whereIn('id', $this->selectedLearners)
            ->where('class_id', $source->id)->where('is_active', true)->get();
        $created = 0;
        foreach ($learners as $learner) {
            $average = $learner->examResults()->whereHas('exam', fn ($q) => $q->where('academic_year', $source->academic_year))
                ->get()->avg(fn ($result) => $result->percentage);
            if ($rule?->minimum_average !== null && ($average === null || $average < $rule->minimum_average)) {
                continue;
            }
            LearnerPromotion::updateOrCreate(
                ['learner_id' => $learner->id, 'from_academic_year' => $source->academic_year, 'to_academic_year' => $this->toYear],
                ['from_class_id' => $source->id, 'to_class_id' => $target->id, 'promotion_rule_id' => $rule?->id, 'status' => 'pending', 'requested_by' => auth()->id(), 'notes' => $average === null ? 'No exam average recorded.' : 'Average: ' . number_format($average, 2)],
            );
            $created++;
        }
        $this->notice = "{$created} promotion request(s) created and awaiting approval.";
        $this->selectedLearners = [];
    }

    public function selectAllLearners(): void
    {
        $this->selectedLearners = Learner::where('class_id', $this->sourceClassId)
            ->where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function approve(int $id): void
    {
        abort_unless($this->canApprove(), 403);
        DB::transaction(function () use ($id): void {
            $promotion = LearnerPromotion::where('status', 'pending')->findOrFail($id);
            $learner = Learner::lockForUpdate()->findOrFail($promotion->learner_id);
            $target = SchoolClass::findOrFail($promotion->to_class_id);
            $learner->update(['class_id' => $target->id, 'grade_level' => $target->grade_level, 'academic_year' => $promotion->to_academic_year]);
            $promotion->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        });
        $this->notice = 'Promotion approved and learner moved to the new class.';
    }

    public function reject(int $id): void
    {
        abort_unless($this->canApprove(), 403);
        LearnerPromotion::where('status', 'pending')->findOrFail($id)->update(['status' => 'rejected', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $this->notice = 'Promotion request rejected.';
    }

    public function saveRule(): void
    {
        abort_unless($this->canManage(), 403);
        $data = $this->validate([
            'ruleForm.name' => ['required', 'string', 'max:150'],
            'ruleForm.from_grade' => ['required', 'string'],
            'ruleForm.to_grade' => ['required', 'string', 'different:ruleForm.from_grade'],
            'ruleForm.minimum_average' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ])['ruleForm'];
        PromotionRule::create($data);
        $this->showRuleForm = false;
        $this->ruleForm = ['name' => '', 'from_grade' => '', 'to_grade' => '', 'minimum_average' => ''];
        $this->notice = 'Promotion rule saved.';
    }

    public function render()
    {
        return view('livewire.admin.promotion-manager', [
            'classes' => SchoolClass::forConfiguredGrades()->where('is_active', true)->orderBy('grade_level')->orderBy('name')->get(),
            'learners' => $this->sourceClassId ? Learner::where('class_id', $this->sourceClassId)->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get() : collect(),
            'rules' => PromotionRule::where('is_active', true)->orderBy('name')->get(),
            'promotions' => LearnerPromotion::with(['learner', 'fromClass', 'toClass'])->latest()->limit(100)->get(),
            'grades' => array_merge(...array_values(config('school.grade_levels'))),
        ])->layout('layouts.admin');
    }

    private function canManage(): bool { return auth()->user()->hasAnyRole(['admin', 'super-admin', 'headteacher', 'principal', 'deputy-principal']); }
    private function canApprove(): bool { return $this->canManage(); }
}
