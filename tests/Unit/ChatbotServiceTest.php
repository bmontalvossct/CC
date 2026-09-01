<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Autochecker\ChatbotService;
use App\Services\Autochecker\ChatToolRegistry;
use App\Services\Autochecker\OllamaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatbotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_streams_chat_request_to_ollama_and_evaluates_model_performance(): void
    {
        $mockOllama = Mockery::mock(OllamaClient::class);
        $mockOllama->shouldReceive('resolveProfileModel')->with('chat')->andReturn('qwen2.5:14b-instruct-q4_K_M');
        $mockOllama->shouldReceive('isLocalEndpoint')->andReturn(true);
        $mockOllama->shouldReceive('chat')->andReturn([
            'message' => ['role' => 'assistant', 'content' => 'Ready to answer.', 'tool_calls' => []],
        ]);
        $mockOllama->shouldReceive('chatStream')->andReturnUsing(function () {
            yield [
                'message' => ['content' => 'Here is guidance on using ClassCheck.'],
            ];
            yield [
                'done' => true,
                'prompt_eval_count' => 120,
                'prompt_eval_duration' => 50000000, // 50ms in ns
                'eval_count' => 80,
                'eval_duration' => 2000000000, // 2s in ns -> 40 tok/s
                'done_reason' => 'stop',
            ];
        });

        $toolRegistry = app(ChatToolRegistry::class);
        $service = new ChatbotService($mockOllama, $toolRegistry);

        $user = User::factory()->create(['name' => 'Prof. Alan']);

        $generator = $service->streamChat(
            user: $user,
            messages: [
                ['role' => 'user', 'content' => 'How do I take attendance?'],
            ],
            scope: ChatbotService::SCOPE_APP_HELP
        );

        $events = iterator_to_array($generator);
        $this->assertNotEmpty($events);

        $eventTypes = array_column($events, 'type');
        $this->assertContains('start', $eventTypes);
        $this->assertContains('delta', $eventTypes);
        $this->assertContains('done', $eventTypes);

        $doneEvent = collect($events)->firstWhere('type', 'done');
        $this->assertNotNull($doneEvent);
        $this->assertEquals('qwen2.5:14b-instruct-q4_K_M', $doneEvent['model']);
        $this->assertEquals(120, $doneEvent['prompt_tokens']);
        $this->assertEquals(80, $doneEvent['eval_tokens']);
        $this->assertEquals(2000.0, $doneEvent['eval_duration_ms']);
        $this->assertEquals(50.0, $doneEvent['prompt_eval_duration_ms']);
        $this->assertEquals(40.0, $doneEvent['eval_tokens_per_sec']);
    }
}
