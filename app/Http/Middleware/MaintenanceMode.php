<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SystemSetting;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->enabled() || $this->allowed($request)) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => (string) SystemSetting::get('maintenance_message', config('school.maintenance_message', 'We are carrying out scheduled maintenance. Please check back shortly.')),
        ], 503)->header('Retry-After', '3600');
    }

    private function enabled(): bool
    {
        try {
            return (bool) ((int) SystemSetting::get('maintenance_mode', 0));
        } catch (\Throwable) {
            return false;
        }
    }

    private function allowed(Request $request): bool
    {
        $path = trim($request->path(), '/');
        if (in_array($path, ['up', 'login', 'maintenance/login', 'school-logo', 'forgot-password', 'logout'], true) || str_starts_with($path, 'password/')) {
            return true;
        }

        $user = $request->user();
        // A platform outage must not give a school-level account a backdoor
        // into the system. Only the platform super-admin may bypass it.
        return $user?->hasRole('super-admin') ?? false;
    }
}
