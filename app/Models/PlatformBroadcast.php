<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformBroadcast extends Model
{
    protected $fillable = [
        'title', 'message', 'send_sms', 'is_pinned', 'status', 'created_by',
        'total_schools', 'schools_notified', 'sent_at',
    ];

    protected $casts = [
        'send_sms' => 'boolean',
        'is_pinned' => 'boolean',
        'sent_at'  => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
