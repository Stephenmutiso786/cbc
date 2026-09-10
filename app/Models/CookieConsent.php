<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CookieConsent extends Model
{
    protected $fillable = [
        'user_id',
        'consent_token',
        'essential',
        'functional',
        'analytics',
        'ip_address',
        'user_agent',
        'consented_at',
    ];

    protected $casts = [
        'essential' => 'boolean',
        'functional' => 'boolean',
        'analytics' => 'boolean',
        'consented_at' => 'datetime',
    ];
}
