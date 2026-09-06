<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplicationReadiness
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up') || is_file(storage_path('framework/app-ready'))) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(
                ['status' => 'starting'],
                Response::HTTP_SERVICE_UNAVAILABLE,
                ['Retry-After' => '2']
            );
        }

        return response()->file(public_path('waking.html'), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
