<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermissionOverride extends Model
{
    protected $fillable = ['role_id', 'permission_ids'];

    protected $casts = ['permission_ids' => 'array'];
}
