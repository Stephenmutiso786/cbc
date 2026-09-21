<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSchool;

class SupportTicket extends Model
{
    use BelongsToSchool;
    protected $fillable = [
        'ticket_number', 'created_by', 'assigned_to', 'subject', 'module', 'device', 'description', 'error_message', 'additional_info',
        'priority', 'status', 'diagnostic_notes', 'resolution', 'closed_at',
    ];

    protected $casts = ['closed_at' => 'datetime'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
}
