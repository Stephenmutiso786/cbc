<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Exam extends Model {
    protected $fillable = ['name','grade_level','class_id','learning_area_id','academic_year','term','exam_type','total_marks','pass_mark','exam_date','start_time','duration_minutes','instructions','status','results_locked_at','locked_by','created_by','results_sms_status','results_sms_queued_at','results_sms_sent_at'];
    protected $casts = ['exam_date' => 'date', 'results_locked_at' => 'datetime', 'results_sms_queued_at' => 'datetime', 'results_sms_sent_at' => 'datetime', 'total_marks' => 'decimal:2', 'pass_mark' => 'decimal:2'];
    public function learningArea() { return $this->belongsTo(LearningArea::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function results()      { return $this->hasMany(ExamResult::class); }
    public function creator()      { return $this->belongsTo(StaffMember::class, 'created_by'); }
    public function lockedBy()     { return $this->belongsTo(StaffMember::class, 'locked_by'); }
    public function isPublished(): bool { return $this->status === 'published'; }
    public function isLocked(): bool { return $this->results_locked_at !== null; }
}
