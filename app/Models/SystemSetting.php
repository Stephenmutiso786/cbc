<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::where('key', $key)->value('value');
        if ($value === null) {
            return $default;
        }

        if (is_string($value) && str_starts_with($value, 'enc:')) {
            try {
                return Crypt::decryptString(substr($value, 4));
            } catch (\Throwable) {
                return $default;
            }
        }

        return $value;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
