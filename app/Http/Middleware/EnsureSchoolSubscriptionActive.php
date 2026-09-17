<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Once a school's plan expires, everyone except super-admin is locked out
 * and redirected to the billing/renewal page — which stays reachable so
 * they can actually pay to get back in.
 */
class EnsureSchoolSubscriptionActive
{
    private const ALLOWED_ROUTE_NAMES = [
        'billing.index', 'school.locked', 'logout', 'legal.acceptance', 'legal.accept', 'legal.terms', 'legal.privacy',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $user->hasRole('super-admin') || ! $user->school_id) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED_ROUTE_NAMES) || $request->is('api/subscription/*') || $request->is('up')) {
            return $next($request);
        }

        $school = $user->school;

        if ($school?->is_locked) {
            return redirect()->route('school.locked');
        }

        if ($school && ! $school->isOnActiveSubscription()) {
            return redirect()->route('billing.index');
        }

        return $next($request);
    }
}
