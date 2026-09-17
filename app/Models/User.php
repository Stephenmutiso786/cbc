<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Concerns\BelongsToSchool;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, BelongsToSchool;

    protected $fillable = ['name', 'email', 'password', 'school_id', 'must_change_password'];
    protected $hidden   = ['password', 'remember_token'];
    protected $casts    = [
        'email_verified_at' => 'datetime',
        'legal_terms_accepted_at' => 'datetime',
        'legal_privacy_accepted_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
    ];

    public function staffMember() { return $this->hasOne(StaffMember::class); }
    public function guardian()    { return $this->hasOne(Guardian::class); }
    public function learner()     { return $this->hasOne(Learner::class); }

    public function levelPortal(): ?array
    {
        foreach (config('school.level_teacher_roles', []) as $role => $portal) {
            if ($this->hasRole($role)) {
                return ['role' => $role] + $portal;
            }
        }

        return null;
    }

    public function gradeBandLevels(): array
    {
        $portal = $this->levelPortal();
        return $portal ? (array) config('school.grade_levels.' . $portal['band'], []) : [];
    }

    public function gradeBandLabel(): ?string
    {
        return $this->levelPortal()['label'] ?? null;
    }
}
