<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Auth;

class Tenant
{
    protected static ?int $overrideId = null;
    protected static bool $overrideActive = false;

    public static function id(): ?int
    {
        if (static::$overrideActive) return static::$overrideId;
        return Auth::check() ? Auth::user()->school_id : null;
    }

    public static function run(?int $schoolId, Closure $callback): mixed
    {
        [$previousId, $previousActive] = [static::$overrideId, static::$overrideActive];
        static::$overrideId = $schoolId;
        static::$overrideActive = true;
        try { return $callback(); }
        finally { static::$overrideId = $previousId; static::$overrideActive = $previousActive; }
    }

    public static function runGlobally(Closure $callback): mixed
    {
        return static::run(null, $callback);
    }
}
