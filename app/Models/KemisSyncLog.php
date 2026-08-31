<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KemisSyncLog extends Model
{
    protected $table = 'kemis_sync_logs';
    protected $guarded = [];
    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime'];
}
