<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable = ['year', 'starts_on', 'ends_on', 'is_active'];

    protected $casts = [
        'year' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function terms()
    {
        return $this->hasMany(AcademicTerm::class)->orderBy('number');
    }
}
