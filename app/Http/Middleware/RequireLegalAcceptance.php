<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireLegalAcceptance
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $this->isExempt($request)) {
            return $next($request);
        }

        if ($user->legal_terms_accepted_at
            && $user->legal_privacy_accepted_at
            && $user->legal_acceptance_version === config('legal.version')) {
            return $next($request);
        }

        return redirect()->guest(route('legal.acceptance'));
    }

    private function isExempt(Request $request): bool
    {
        return $request->is('up')
            || $request->is('school-logo')
            || $request->is('files/*')
            || $request->routeIs('login', 'logout', 'legal.*', 'password.*', 'forgot-password', 'maintenance.login');
    }
}
