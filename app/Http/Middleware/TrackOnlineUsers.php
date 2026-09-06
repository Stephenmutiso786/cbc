<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TrackOnlineUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            try {
                if (Schema::hasColumn('users', 'last_seen_at')) {
                    $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();
                }
            } catch (\Throwable) {
                // Presence must never make the dashboard unavailable during a migration.
            }
        }

        return $next($request);
    }
}
