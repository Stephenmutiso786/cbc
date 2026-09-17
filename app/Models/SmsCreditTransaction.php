<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsCreditTransaction extends Model
{
    protected $fillable = ['school_id', 'amount', 'type', 'amount_paid', 'payment_reference', 'note', 'created_by'];

    protected $casts = ['amount_paid' => 'decimal:2'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
