<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! filter_var(env('API_PERFORMANCE_LOG_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            return $next($request);
        }

        $startedAt = microtime(true);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $next($request);

        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        $queryCount = count($queryLog);
        $queriesDurationMs = round(array_sum(array_column($queryLog, 'time')), 2);

        $payload = [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'query_count' => $queryCount,
            'queries_duration_ms' => $queriesDurationMs,
        ];

        $slowThresholdMs = (float) env('API_SLOW_REQUEST_MS', 400);

        if ($durationMs >= $slowThresholdMs) {
            Log::warning('api.performance.slow', $payload);
        } else {
            Log::info('api.performance', $payload);
        }

        return $response;
    }
}
