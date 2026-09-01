<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('production') && !$request->secure()) {
            return redirect()->secure($request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
