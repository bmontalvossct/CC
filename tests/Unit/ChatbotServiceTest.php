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

    public function test_streams_chat_with_active_section_having_passing_rates_in_grading_weights(): void
    {
        $mockOllama = Mockery::mock(OllamaClient::class);
        $mockOllama->shouldReceive('resolveProfileModel')->with('chat')->andReturn('qwen2.5:14b-instruct-q4_K_M');
        $mockOllama->shouldReceive('isLocalEndpoint')->andReturn(true);
        $mockOllama->shouldReceive('chat')->andReturn([
            'message' => ['role' => 'assistant', 'content' => 'I see your section weights.', 'tool_calls' => []],
        ]);
        $mockOllama->shouldReceive('chatStream')->andReturnUsing(function () {
            yield [
                'message' => ['content' => 'Your quiz passing rate is 75%.'],
            ];
            yield [
                'done' => true,
                'prompt_eval_count' => 100,
                'prompt_eval_duration' => 40000000,
                'eval_count' => 50,
                'eval_duration' => 1000000000,
                'done_reason' => 'stop',
            ];
        });

        $toolRegistry = app(ChatToolRegistry::class);
        $service = new ChatbotService($mockOllama, $toolRegistry);

        $user = User::factory()->create(['name' => 'Prof. Alan']);
        $term = \App\Models\AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
        ]);
        $section = \App\Models\Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'name' => 'BSIT 3A',
            'subject_code' => 'IT 301',
            'subject_title' => 'Web Systems and Technologies',
            'room' => 'Lab 3',
            'grading_weights' => [
                'activity' => 20,
                'quiz' => 20,
                'exam' => 30,
                'project' => 15,
                'attendance' => 10,
                'recitation' => 5,
                'passing_rates' => [
                    'quiz' => 80,
                    'activity' => 70,
                    'project' => 75,
                    'exam' => 75,
                ],
                'reporting_frequency' => 'twice_per_sem',
            ],
        ]);

        $generator = $service->streamChat(
            user: $user,
            messages: [
                ['role' => 'user', 'content' => 'What is my passing rate?'],
            ],
            scope: ChatbotService::SCOPE_CURRENT_SECTION,
            sectionId: $section->id
        );

        $events = iterator_to_array($generator);
        $this->assertNotEmpty($events);

        $errorEvent = collect($events)->firstWhere('type', 'error');
        $this->assertNull($errorEvent, 'Should not yield an error event: ' . ($errorEvent['message'] ?? ''));

        $doneEvent = collect($events)->firstWhere('type', 'done');
        $this->assertNotNull($doneEvent);
    }
}
