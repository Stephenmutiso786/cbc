<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingScale extends Model
{
    protected $fillable = ['name', 'description', 'type', 'bands', 'is_active'];
    protected $casts = ['bands' => 'array', 'is_active' => 'boolean'];

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_grading_scales', 'grading_scale_id', 'class_id')
            ->withPivot('academic_year');
    }

    public function gradeForPercent(float $percent): ?string
    {
        foreach ($this->bands ?? [] as $band) {
            if ($percent >= (float) ($band['min'] ?? 0) && $percent <= (float) ($band['max'] ?? 100)) {
                return $band['code'] ?? null;
            }
        }
        return null;
    }

    public function commentForCode(?string $code): ?string
    {
        if (! $code) return null;
        foreach ($this->bands ?? [] as $band) {
            if (($band['code'] ?? null) === $code) return $band['label'] ?? null;
        }
        return null;
    }
}
