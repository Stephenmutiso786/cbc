<?php

namespace App\Http\Middleware;

use App\Support\SchoolSettingsLoader;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;

class LoadSchoolSettings
{
    public function handle(Request $request, Closure $next)
    {
        if (($schoolId = Tenant::id()) !== null && !env('SKIP_DB_SETTINGS_BOOT', false) && !$request->is('up')) SchoolSettingsLoader::for($schoolId);
        return $next($request);
    }
}
