<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSchool;

class AcademicTerm extends Model
{
    use BelongsToSchool;
    protected $fillable = ['academic_year_id', 'number', 'name', 'starts_on', 'ends_on', 'is_active'];

    protected $casts = [
        'number' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
