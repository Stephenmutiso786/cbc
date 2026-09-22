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

    protected $fillable = ['name', 'email', 'password', 'school_id', 'status', 'must_change_password'];
    protected $hidden   = ['password', 'remember_token'];
    protected $casts    = [
        'email_verified_at' => 'datetime',
        'legal_terms_accepted_at' => 'datetime',
        'legal_privacy_accepted_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
        'last_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function staffMember() { return $this->hasOne(StaffMember::class); }
    public function guardian()    { return $this->hasOne(Guardian::class); }
    public function learner()     { return $this->hasOne(Learner::class); }

    /**
     * Return the staff profile that owns this teacher account.
     *
     * Older schools can have a staff record and a separately-created teacher
     * login with the same email address but no `staff_members.user_id` link.
     * Subject allocations belong to the staff record, so leaving that link
     * absent makes a legitimately allocated teacher appear to have zero
     * subjects.  Only repair an unlinked profile in the same school with an
     * exact, case-insensitive email match; never guess from a person's name.
     */
    public function resolvedStaffMember(): ?StaffMember
    {
        $staff = $this->relationLoaded('staffMember')
            ? $this->getRelation('staffMember')
            : $this->staffMember()->first();

        if ($staff || ! $this->school_id || blank($this->email)) {
            return $staff;
        }

        $staff = StaffMember::withoutSchoolScope()
            ->where('school_id', $this->school_id)
            ->whereNull('user_id')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($this->email)])
            ->first();

        if ($staff) {
            $staff->forceFill(['user_id' => $this->id])->saveQuietly();
            $this->setRelation('staffMember', $staff);
        }

        return $staff;
    }

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
