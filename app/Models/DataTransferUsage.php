<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataTransferUsage extends Model
{
    protected $fillable = ['usage_date', 'bytes'];

    protected $casts = [
        'usage_date' => 'date',
        'bytes' => 'integer',
    ];
}
