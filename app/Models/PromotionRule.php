<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionRule extends Model
{
    protected $fillable = ['name', 'minimum_average', 'from_grade', 'to_grade', 'is_active'];
    protected $casts = ['minimum_average' => 'decimal:2', 'is_active' => 'boolean'];
}
