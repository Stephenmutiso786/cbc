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

        // Never call Auth::check()/Auth::user() here. This method runs from
        // Eloquent's global scope, including while Laravel is loading the
        // User model during login. Asking the guard to resolve a user at that
        // point starts another User query and recurses until PHP runs out of
        // memory. `hasUser()` only reads the guard's already-loaded user.
        $guard = Auth::guard();
        if (method_exists($guard, 'hasUser') && $guard->hasUser()) {
            return $guard->user()?->school_id;
        }

        return null;
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
