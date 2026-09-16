<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSchool;

class NotificationLog extends Model
{
    use BelongsToSchool;
    protected $fillable = ['notification_id', 'recipient_phone', 'recipient_email', 'channel', 'status', 'provider_message_id', 'error_message', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
    public function notification() { return $this->belongsTo(SchoolNotification::class); }
}
