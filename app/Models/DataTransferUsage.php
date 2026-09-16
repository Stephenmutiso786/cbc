<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSchool;

class DataTransferUsage extends Model
{
    use BelongsToSchool;
    protected $fillable = ['usage_date', 'bytes'];

    protected $casts = [
        'usage_date' => 'date',
        'bytes' => 'integer',
    ];
}
