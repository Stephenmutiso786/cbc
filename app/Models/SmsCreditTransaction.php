<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsCreditTransaction extends Model
{
    protected $fillable = ['school_id', 'amount', 'type', 'note', 'created_by'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

