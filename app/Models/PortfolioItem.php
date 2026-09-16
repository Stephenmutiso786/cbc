<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSchool;
class PortfolioItem extends Model {
    use BelongsToSchool;
    protected $fillable = ['learner_id','learning_area_id','title','description','item_type','term','academic_year','uploaded_by'];
    public function learner()      { return $this->belongsTo(Learner::class); }
    public function learningArea() { return $this->belongsTo(LearningArea::class); }
    public function uploadedBy()   { return $this->belongsTo(StaffMember::class, 'uploaded_by'); }
}
