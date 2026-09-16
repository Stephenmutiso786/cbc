<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSchool;

class SchoolSettingAsset extends Model
{
    use BelongsToSchool;
    protected $fillable = ['key', 'data'];
}
