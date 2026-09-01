<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class SchoolClass extends Model {
    use HasFactory;
    protected $fillable = ['name','grade_level','stream','academic_year','class_teacher_id','capacity','is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function classTeacher() { return $this->belongsTo(StaffMember::class, 'class_teacher_id'); }
    public function learners()     { return $this->hasMany(Learner::class, 'class_id'); }
    public function timetable()    { return $this->hasMany(TimetableSlot::class, 'class_id'); }
    public function assessments()  { return $this->hasMany(Assessment::class, 'class_id'); }
    public function learningAreas() { return $this->belongsToMany(LearningArea::class, 'class_learning_areas', 'class_id', 'learning_area_id')->withPivot(['lessons_per_week', 'is_active'])->wherePivot('is_active', true); }
    public function gradingScales() { return $this->belongsToMany(GradingScale::class, 'class_grading_scales', 'class_id', 'grading_scale_id')->withPivot('academic_year'); }
    public function gradingScale() { return $this->gradingScales()->wherePivot('academic_year', (string) config('school.academic_year'))->where('grading_scales.is_active', true)->latest('grading_scales.id'); }
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeForYear($q, $y) { return $q->where('academic_year', $y); }
    public function scopeForConfiguredGrades($q) { return $q->whereIn('grade_level', array_merge(...array_values(config('school.grade_levels')))); }
}
