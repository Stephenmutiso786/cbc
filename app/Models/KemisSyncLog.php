<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSchool;

class KemisSyncLog extends Model
{
    use BelongsToSchool;
    protected $table = 'kemis_sync_logs';
    protected $guarded = [];
    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime'];
}
