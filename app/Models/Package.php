<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'name', 'description', 'price', 'billing_cycle',
        'max_students', 'max_staff', 'sms_credits_granted', 'features', 'is_active',
    ];

    protected $casts = [
        'price'    => 'decimal:2',
        'features' => 'array',
        'is_active'=> 'boolean',
    ];

    public function schools()
    {
        return $this->hasMany(School::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }
}

