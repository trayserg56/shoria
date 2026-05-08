<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $thresholdMs = (int) config('app.slow_request_log_ms', 0);

        if ($thresholdMs <= 0) {
            return $next($request);
        }

        $started = microtime(true);
        $response = $next($request);
        $durationMs = (microtime(true) - $started) * 1000;

        if ($durationMs >= $thresholdMs) {
            Log::warning('Slow HTTP request', [
                'duration_ms' => round($durationMs, 2),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
        }

        return $response;
    }
}
