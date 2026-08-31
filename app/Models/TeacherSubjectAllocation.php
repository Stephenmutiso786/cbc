<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherSubjectAllocation extends Model
{
    protected $fillable = ['teacher_id', 'class_id', 'learning_area_id', 'term', 'academic_year', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function teacher() { return $this->belongsTo(StaffMember::class, 'teacher_id'); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function learningArea() { return $this->belongsTo(LearningArea::class); }
}
