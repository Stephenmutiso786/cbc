<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class TimetableVersion extends Model
{
    use BelongsToSchool;

    protected $fillable = ['academic_year', 'term', 'version_number', 'state', 'generation_options', 'conflict_report', 'lesson_count', 'generated_by', 'generated_at', 'verified_by', 'verified_at', 'published_by', 'published_at', 'archived_by', 'archived_at'];
    protected $casts = ['generation_options' => 'array', 'conflict_report' => 'array', 'generated_at' => 'datetime', 'verified_at' => 'datetime', 'published_at' => 'datetime', 'archived_at' => 'datetime'];

    public function slots() { return $this->hasMany(TimetableSlot::class); }
}
