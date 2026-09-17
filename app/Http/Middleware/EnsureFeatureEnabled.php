<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $user = $request->user();

        if (! $user || $user->hasRole('super-admin') || ! $user->school_id) {
            return $next($request);
        }

        abort_unless($user->school?->hasFeature($feature), 403, 'Your school\'s plan does not include this feature. Ask your administrator to upgrade the plan.');

        return $next($request);
    }
}

