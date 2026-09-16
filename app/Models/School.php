<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $fillable = ['name', 'slug', 'type', 'motto', 'address', 'phone', 'email', 'logo_data', 'is_active', 'trial_ends_at'];
    protected $casts = ['is_active' => 'boolean', 'trial_ends_at' => 'datetime'];

    public function users() { return $this->hasMany(User::class); }
    public function learners() { return $this->hasMany(Learner::class); }
    public function staffMembers() { return $this->hasMany(StaffMember::class); }
}
