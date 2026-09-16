<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $builder): void {
            if (($schoolId = Tenant::id()) !== null) {
                $builder->where($builder->getModel()->getTable() . '.school_id', $schoolId);
            }
        });

        static::creating(function ($model): void {
            if (empty($model->school_id) && Tenant::id() !== null) {
                $model->school_id = Tenant::id();
            }
        });
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function scopeWithoutSchoolScope(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope('school');
    }
}
