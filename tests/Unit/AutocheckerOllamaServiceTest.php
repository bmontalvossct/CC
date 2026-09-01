<?php

namespace Tests\Unit;

use App\Services\Autochecker\OllamaClient;
use App\Services\Autochecker\OllamaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutocheckerOllamaServiceTest extends TestCase
{
    public function test_ollama_client_ping_detects_online_ollama(): void
    {
        Http::fake([
            'http://127.0.0.1:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'qwen2.5:14b-instruct-q4_K_M', 'size' => 9000000000],
                    ['name' => 'qwen2.5-coder:7b', 'size' => 4500000000],
                ],
            ], 200),
        ]);

        $client = app(OllamaClient::class);
        $res = $client->ping();

        $this->assertTrue($res['online']);
        $this->assertTrue($res['is_local']);
    }

    public function test_evaluates_submission_and_parses_json_schema(): void
    {
        Http::fake([
            'http://127.0.0.1:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'qwen2.5-coder:7b', 'size' => 4500000000],
                ],
            ], 200),
            'http://127.0.0.1:11434/api/chat' => Http::response([
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'criterion_evaluations' => [
                            [
                                'criterion_id' => 'crit_func',
                                'score' => 30.0,
                                'rationale' => 'Full base case and correct recursion logic.',
                                'evidence_quote' => 'return 1 if n <= 1 else n * factorial(n-1)',
                            ],
                            [
                                'criterion_id' => 'crit_code',
                                'score' => 15.5,
                                'rationale' => 'Clean one-liner syntax but could use type hinting.',
                                'evidence_quote' => 'def factorial(n):',
                            ],
                        ],
                        'overall_summary' => 'Good recursive implementation with clean syntax.',
                        'key_strengths' => ['Clear base case'],
                        'key_improvements' => ['Add type hints'],
                    ]),
                ],
            ], 200),
        ]);

        $service = app(OllamaService::class);
        $result = $service->evaluateSubmission(
            content: "1 | def factorial(n):\n2 |     return 1 if n <= 1 else n * factorial(n-1)",
            filename: '2024-00123_Activity1.py',
            maxPoints: 50.0,
            rubricCriteria: [
                ['id' => 'crit_func', 'name' => 'Functionality', 'max_points' => 30.0],
                ['id' => 'crit_code', 'name' => 'Code Quality', 'max_points' => 20.0],
            ],
        );

        $this->assertEquals(45.5, $result['score']);
        $this->assertSame('Good recursive implementation with clean syntax.', $result['overall_summary']);
        $this->assertCount(2, $result['criteria_scores']);
    }
}
