<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Exam extends Model {
    protected $fillable = ['name','exam_group_id','grade_level','class_id','learning_area_id','academic_year','term','exam_type','total_marks','pass_mark','exam_date','start_time','duration_minutes','instructions','status','exam_state','marks_status','marks_submitted_at','marks_submitted_by','marks_reviewed_at','marks_reviewed_by','marks_review_comment','results_locked_at','locked_by','created_by','results_sms_status','results_sms_queued_at','results_sms_sent_at'];
    protected $casts = ['exam_date' => 'date', 'marks_submitted_at' => 'datetime', 'marks_reviewed_at' => 'datetime', 'results_locked_at' => 'datetime', 'results_sms_queued_at' => 'datetime', 'results_sms_sent_at' => 'datetime', 'total_marks' => 'decimal:2', 'pass_mark' => 'decimal:2'];
    public function learningArea() { return $this->belongsTo(LearningArea::class); }
    public function examGroup() { return $this->belongsTo(self::class, 'exam_group_id'); }
    public function groupedSubjects() { return $this->hasMany(self::class, 'exam_group_id'); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function results()      { return $this->hasMany(ExamResult::class); }
    public function creator()      { return $this->belongsTo(StaffMember::class, 'created_by'); }
    public function lockedBy()     { return $this->belongsTo(StaffMember::class, 'locked_by'); }
    public function isPublished(): bool { return $this->status === 'published'; }
    public function isLocked(): bool { return $this->results_locked_at !== null; }
    public function groupExamIds()
    {
        $rootId = $this->exam_group_id ?: $this->id;
        return self::where('id', $rootId)->orWhere('exam_group_id', $rootId)->pluck('id');
    }

    public function isGroupMaster(): bool { return $this->exam_group_id === null; }
}
