<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $fillable = ['user_id', 'method', 'path', 'status', 'ip_address', 'user_agent', 'duration_ms', 'context'];

    protected $casts = ['context' => 'array', 'duration_ms' => 'float'];

    public function user() { return $this->belongsTo(User::class); }
}
