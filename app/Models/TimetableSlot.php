<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSchool;
class TimetableSlot extends Model {
    use BelongsToSchool;
    protected $fillable = ['timetable_version_id','class_id','learning_area_id','teacher_id','day_of_week','start_time','end_time','venue','is_double','academic_year','term','is_active'];
    protected $casts = ['is_active' => 'boolean', 'is_double' => 'boolean'];
    public function version()      { return $this->belongsTo(TimetableVersion::class, 'timetable_version_id'); }
    public function schoolClass()  { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function learningArea() { return $this->belongsTo(LearningArea::class); }
    public function teacher()      { return $this->belongsTo(StaffMember::class, 'teacher_id'); }
}
