<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->enabled() || $this->allowed($request)) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => (string) config('school.maintenance_message', 'We are carrying out scheduled maintenance. Please check back shortly.'),
        ], 503)->header('Retry-After', '3600');
    }

    private function enabled(): bool
    {
        try {
            return (bool) ((int) DB::table('school_settings')->where('key', 'maintenance_mode')->value('value'));
        } catch (\Throwable) {
            return false;
        }
    }

    private function allowed(Request $request): bool
    {
        $path = trim($request->path(), '/');
        if (in_array($path, ['up', 'login', 'forgot-password'], true) || str_starts_with($path, 'password/')) {
            return true;
        }

        return $request->user()?->hasAnyRole([
            'admin', 'super-admin', 'headteacher', 'principal', 'deputy-principal', 'deputy',
        ]) ?? false;
    }
}
