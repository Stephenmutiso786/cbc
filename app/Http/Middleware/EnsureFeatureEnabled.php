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

        if (! $user->school?->hasFeature($feature)) {
            // A direct link should explain the plan requirement rather than
            // exposing Laravel's generic forbidden error page. Livewire and
            // JSON calls retain their 403 so they cannot silently continue.
            if ($request->expectsJson() || $request->header('X-Livewire')) {
                abort(403, 'Your school plan does not include this feature.');
            }

            return redirect()->route('feature.upgrade', ['feature' => $feature]);
        }

        return $next($request);
    }
}
