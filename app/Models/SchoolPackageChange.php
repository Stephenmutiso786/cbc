<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolPackageChange extends Model
{
    protected $fillable = ['school_id', 'package_id', 'effective_date', 'expires_at', 'changed_by', 'note'];

    protected $casts = [
        'effective_date' => 'date',
        'expires_at'     => 'date',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

