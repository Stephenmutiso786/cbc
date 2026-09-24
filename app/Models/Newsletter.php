<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'reference', 'issued_on', 'salutation', 'subject', 'body', 'closing', 'signatory_name', 'signatory_title', 'copies', 'created_by', 'updated_by'];

    protected $casts = ['issued_on' => 'date', 'copies' => 'array'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
