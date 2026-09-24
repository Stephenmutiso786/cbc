<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'reference', 'issued_on', 'salutation', 'subject', 'body', 'closing', 'signatory_name', 'signatory_title', 'copies', 'is_published', 'published_at', 'published_by', 'created_by', 'updated_by'];

    protected $casts = ['issued_on' => 'date', 'copies' => 'array', 'is_published' => 'boolean', 'published_at' => 'datetime'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function scopePublished($query) { return $query->where('is_published', true); }
}
