<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearnerPromotion extends Model
{
    protected $fillable = [
        'learner_id', 'from_class_id', 'to_class_id', 'from_academic_year',
        'to_academic_year', 'promotion_rule_id', 'status', 'requested_by',
        'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = ['approved_at' => 'datetime'];
    public function learner() { return $this->belongsTo(Learner::class); }
    public function fromClass() { return $this->belongsTo(SchoolClass::class, 'from_class_id'); }
    public function toClass() { return $this->belongsTo(SchoolClass::class, 'to_class_id'); }
    public function rule() { return $this->belongsTo(PromotionRule::class, 'promotion_rule_id'); }
}
