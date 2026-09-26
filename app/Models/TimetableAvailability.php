<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class TimetableAvailability extends Model
{
    use BelongsToSchool;

    protected $fillable = ['resource_type', 'resource_key', 'day_of_week', 'start_time', 'end_time', 'is_available', 'reason', 'created_by'];
    protected $casts = ['is_available' => 'boolean'];
}
