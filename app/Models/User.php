<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden   = ['password', 'remember_token'];
    protected $casts    = ['email_verified_at' => 'datetime', 'password' => 'hashed'];

    public function staffMember() { return $this->hasOne(StaffMember::class); }
    public function guardian()    { return $this->hasOne(Guardian::class); }

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
