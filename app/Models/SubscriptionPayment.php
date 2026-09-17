<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'package_id', 'student_count', 'amount', 'phone',
        'checkout_request_id', 'merchant_request_id', 'mpesa_receipt_number',
        'status', 'failure_reason', 'period_start', 'period_end', 'initiated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'amount'       => 'decimal:2',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}

