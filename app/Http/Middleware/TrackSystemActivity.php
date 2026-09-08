<?php

namespace App\Http\Middleware;

use App\Models\SystemLog;
use Closure;
use Illuminate\Http\Request;

class TrackSystemActivity
{
    public function handle(Request $request, Closure $next)
    {
        $started = microtime(true);
        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $this->record($request, 500, $started, ['exception' => $exception::class]);
            throw $exception;
        }

        if (! $request->is('presence/ping') && ! $request->is('_ignition/*')) {
            $this->record($request, $response->getStatusCode(), $started);
        }

        return $response;
    }

    private function record(Request $request, int $status, float $started, array $context = []): void
    {
        try {
            SystemLog::create([
                'user_id' => $request->user()?->id,
                'method' => $request->method(),
                'path' => '/' . ltrim($request->path(), '/'),
                'status' => $status,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'duration_ms' => round((microtime(true) - $started) * 1000, 2),
                'context' => $context ?: null,
            ]);
        } catch (\Throwable) {
            // Logging must never break the request or deployment boot.
        }
    }
}
