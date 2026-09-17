<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class School extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'motto', 'address', 'phone', 'email',
        'logo_data', 'is_active', 'trial_ends_at', 'package_id', 'package_expires_at', 'sms_credits',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'trial_ends_at'      => 'datetime',
        'package_expires_at' => 'date',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function learners()
    {
        return $this->hasMany(Learner::class);
    }

    public function staffMembers()
    {
        return $this->hasMany(StaffMember::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function packageChanges()
    {
        return $this->hasMany(SchoolPackageChange::class)->latest();
    }

    public function smsCreditTransactions()
    {
        return $this->hasMany(SmsCreditTransaction::class)->latest();
    }

    public function hasFeature(string $key): bool
    {
        return $this->isOnActiveSubscription() && ($this->package?->hasFeature($key) ?? false);
    }

    public function isOnActiveSubscription(): bool
    {
        // No package assigned yet (e.g. brand new school) or no expiry set
        // at all is treated as inactive — a school must be on a paid plan.
        if (! $this->package_id || ! $this->package_expires_at) {
            return false;
        }

        return ! $this->packageExpired();
    }

    public function activeStudentCount(): int
    {
        return $this->learners()->where('is_active', true)->count();
    }

    /** What one term on the given (or currently assigned) package would cost right now. */
    public function renewalAmountFor(?Package $package = null): float
    {
        $package ??= $this->package;
        if (! $package) {
            return 0.0;
        }

        return round((float) $package->price * max($this->activeStudentCount(), 1), 2);
    }

    public function subscriptionStatusLabel(): string
    {
        if (! $this->package_id) {
            return 'No plan selected';
        }
        if (! $this->package_expires_at) {
            return 'Not yet activated';
        }
        if ($this->packageExpired()) {
            return 'Expired ' . $this->package_expires_at->diffForHumans();
        }

        return 'Active — renews ' . $this->package_expires_at->diffForHumans();
    }

    public function packageExpired(): bool
    {
        return $this->package_expires_at !== null && $this->package_expires_at->isPast();
    }

    public function hasReachedStudentLimit(): bool
    {
        $limit = $this->package?->max_students;
        if ($limit === null) {
            return false;
        }

        return $this->learners()->where('is_active', true)->count() >= $limit;
    }

    public function hasReachedStaffLimit(): bool
    {
        $limit = $this->package?->max_staff;
        if ($limit === null) {
            return false;
        }

        return $this->staffMembers()->where('is_active', true)->count() >= $limit;
    }

    /**
     * Assign (or change) this school's package, logging the change and
     * crediting the new package's included SMS units.
     */
    public function assignPackage(Package $package, ?string $expiresAt, ?int $changedBy, ?string $note = null): void
    {
        DB::transaction(function () use ($package, $expiresAt, $changedBy, $note) {
            $this->update(['package_id' => $package->id, 'package_expires_at' => $expiresAt]);

            SchoolPackageChange::create([
                'school_id'      => $this->id,
                'package_id'     => $package->id,
                'effective_date' => now()->toDateString(),
                'expires_at'     => $expiresAt,
                'changed_by'     => $changedBy,
                'note'           => $note,
            ]);

            if ($package->sms_credits_granted > 0) {
                $this->addSmsCredits($package->sms_credits_granted, 'package_grant', $changedBy, "Included with \"{$package->name}\" package");
            }
        });
    }

    public function addSmsCredits(int $amount, string $type, ?int $createdBy, ?string $note = null): void
    {
        if ($amount === 0) {
            return;
        }

        DB::transaction(function () use ($amount, $type, $createdBy, $note) {
            $this->increment('sms_credits', $amount);
            SmsCreditTransaction::create([
                'school_id'  => $this->id,
                'amount'     => $amount,
                'type'       => $type,
                'note'       => $note,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Credit a school's SMS wallet after the platform has confirmed its
     * payment. The payment trail is deliberately kept with the allocation so
     * a school cannot be credited without a super-admin audit record.
     */
    public function allocateSmsCredits(
        int $units,
        ?float $amountPaid,
        ?string $paymentReference,
        ?int $createdBy,
        ?string $note = null,
    ): void {
        if ($units <= 0) {
            return;
        }

        DB::transaction(function () use ($units, $amountPaid, $paymentReference, $createdBy, $note): void {
            $this->increment('sms_credits', $units);
            SmsCreditTransaction::create([
                'school_id' => $this->id,
                'amount' => $units,
                'type' => 'topup',
                'amount_paid' => $amountPaid,
                'payment_reference' => $paymentReference,
                'note' => $note,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Deduct SMS credits for actual usage. Returns false (deducting nothing)
     * if the school doesn't have enough — callers must not send the message
     * in that case.
     */
    public function deductSmsCredits(int $amount, ?string $note = null): bool
    {
        if ($amount <= 0) {
            return true;
        }

        return DB::transaction(function () use ($amount, $note) {
            $fresh = static::whereKey($this->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->sms_credits < $amount) {
                return false;
            }

            $fresh->decrement('sms_credits', $amount);
            $this->sms_credits = $fresh->sms_credits;
            SmsCreditTransaction::create([
                'school_id'  => $this->id,
                'amount'     => -$amount,
                'type'       => 'usage',
                'note'       => $note,
                'created_by' => null,
            ]);

            return true;
        });
    }
}
