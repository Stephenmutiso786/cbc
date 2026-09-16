<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSchool;

class ExamTimetable extends Model
{
    use BelongsToSchool;
    protected $table = 'exam_timetable';

    protected $fillable = [
        'exam_id', 'class_id', 'invigilator_id', 'venue', 'date', 'start_time', 'end_time', 'is_published',
    ];

    protected $casts = ['date' => 'date', 'is_published' => 'boolean'];

    public function exam() { return $this->belongsTo(Exam::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function invigilator() { return $this->belongsTo(StaffMember::class, 'invigilator_id'); }
}
