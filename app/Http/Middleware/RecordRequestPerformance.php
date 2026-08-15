<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RecordRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $queryCount = 0;
        $queryDuration = 0.0;

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryDuration): void {
            $queryCount++;
            $queryDuration += $query->time;
        });

        $response = $next($request);
        $duration = (hrtime(true) - $startedAt) / 1_000_000;

        $response->headers->set(
            'Server-Timing',
            sprintf('app;dur=%.1f, db;dur=%.1f;desc=%d_queries', $duration, $queryDuration, $queryCount),
        );

        if ($duration >= config('performance.slow_request_ms') || $response->getStatusCode() >= 500) {
            $route = $request->route();

            Log::warning('request.performance', [
                'event' => 'slow_request',
                'route' => $route?->getName() ?? $route?->uri() ?? 'unmatched',
                'status' => $response->getStatusCode(),
                'duration_ms' => round($duration, 1),
                'query_count' => $queryCount,
                'query_duration_ms' => round($queryDuration, 1),
                'x_vercel_id' => $request->header('x-vercel-id'),
            ]);
        }

        return $response;
    }
}
