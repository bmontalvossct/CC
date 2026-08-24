<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RequestPerformanceTest extends TestCase
{
    public function test_dynamic_responses_expose_server_timing_and_log_only_structured_metrics(): void
    {
        config(['performance.slow_request_ms' => 0]);
        Log::spy();

        $response = $this->withHeader('x-vercel-id', 'sin1::test-request')->get('/');

        $response->assertOk()->assertHeader('Server-Timing');
        $this->assertMatchesRegularExpression(
            '/^app;dur=\d+\.\d, db;dur=\d+\.\d;desc=\d+_queries$/',
            $response->headers->get('Server-Timing'),
        );
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'request.performance'
                && $context['event'] === 'slow_request'
                && $context['route'] === 'home'
                && $context['status'] === 200
                && $context['x_vercel_id'] === 'sin1::test-request'
                && array_keys($context) === [
                    'event', 'route', 'status', 'duration_ms', 'query_count', 'query_duration_ms', 'x_vercel_id',
                ];
        });
    }

    public function test_fast_requests_are_not_logged_by_default(): void
    {
        config(['performance.slow_request_ms' => PHP_INT_MAX]);
        Log::spy();

        $this->get('/up')->assertOk()->assertHeader('Server-Timing');

        Log::shouldNotHaveReceived('warning');
    }
}
