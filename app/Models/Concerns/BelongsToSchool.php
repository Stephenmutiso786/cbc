<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait BelongsToSchool
{
    /** @var array<string, bool> */
    protected static array $schoolColumnExists = [];

    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $builder): void {
            $table = $builder->getModel()->getTable();
            // A deploy can briefly serve a request while migrations are being
            // applied. Never turn that into a dashboard 500 solely because a
            // newly introduced tenant column is not present yet.
            $hasSchoolColumn = static::$schoolColumnExists[$table] ??= Schema::hasColumn($table, 'school_id');
            if ($hasSchoolColumn && ($schoolId = Tenant::id()) !== null) {
                $builder->where($table . '.school_id', $schoolId);
            }
        });

        static::creating(function ($model): void {
            $table = $model->getTable();
            $hasSchoolColumn = static::$schoolColumnExists[$table] ??= Schema::hasColumn($table, 'school_id');
            if ($hasSchoolColumn && empty($model->school_id) && Tenant::id() !== null) {
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
