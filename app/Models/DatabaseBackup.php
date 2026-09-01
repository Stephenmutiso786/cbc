<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatabaseBackup extends Model
{
    protected $fillable = [
        'driver',
        'database_size_bytes',
        'archive_size_bytes',
        'drive_file_id',
        'status',
        'error',
    ];

    protected $casts = [
        'database_size_bytes' => 'integer',
        'archive_size_bytes' => 'integer',
    ];
}
