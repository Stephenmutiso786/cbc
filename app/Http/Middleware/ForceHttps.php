<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        // Prefer the public host Railway actually used for this request. This
        // prevents stale APP_URL values from breaking every generated asset.
        if ($request->getHost() !== '') {
            $applicationUrl = $request->getSchemeAndHttpHost();
            config()->set('app.url', $applicationUrl);
            config()->set('app.asset_url', $applicationUrl);
            config()->set('filesystems.disks.public.url', $applicationUrl . '/storage');
            URL::forceRootUrl($applicationUrl);
        }

        if (app()->environment('production') && !$request->secure()) {
            return redirect()->secure($request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
