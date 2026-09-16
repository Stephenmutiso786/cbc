<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSchool;

class PromotionRule extends Model
{
    use BelongsToSchool;
    protected $fillable = ['name', 'minimum_average', 'from_grade', 'to_grade', 'is_active'];
    protected $casts = ['minimum_average' => 'decimal:2', 'is_active' => 'boolean'];
}
