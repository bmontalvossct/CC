<?php

namespace Tests\Unit;

use App\Services\Autochecker\AiDocumentGraderService;
use App\Services\Autochecker\FileContentExtractorService;
use App\Services\Autochecker\OllamaClient;
use Mockery;
use Tests\TestCase;

class AiDocumentGraderSafetyTest extends TestCase
{
    private function grader(?string $feedback = null, bool $online = true): AiDocumentGraderService
    {
        $client = Mockery::mock(OllamaClient::class);
        $client->shouldReceive('ping')->andReturn(['online' => $online]);
        $client->shouldReceive('chat')->andReturn(['message' => ['content' => json_encode(['score' => 8, 'remarks' => $feedback])]]);
        return new class($client, Mockery::mock(FileContentExtractorService::class)) extends AiDocumentGraderService {
            public function evaluate(?string $rubric): array
            {
                return $this->evaluateDocument('Student evidence', 'output.txt', 'Activity 1', 'activity', 10, rubricContent: $rubric);
            }
        };
    }

    public function test_missing_activity_file_stops_checking(): void
    {
        $activity = new \App\Models\Assessment;
        $activity->activity_file_path = 'missing-instructions-'.uniqid().'.txt';
        $activity->activity_file_name = 'instructions.txt';
        $this->expectExceptionMessage('The activity instructions file could not be read.');
        $this->grader()->activityInstructions($activity);
    }

    public function test_activity_file_is_included_in_the_grading_prompt(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('instructions.txt', 'Implement addition and subtraction.');
        $activity = new \App\Models\Assessment;
        $activity->activity_file_path = 'instructions.txt';
        $activity->activity_file_name = 'instructions.txt';
        $client = Mockery::mock(OllamaClient::class);
        $client->shouldReceive('ping')->andReturn(['online' => true]);
        $client->shouldReceive('chat')->once()->withArgs(function ($profile, $messages) {
            return str_contains($messages[1]['content'], 'Implement addition and subtraction.');
        })->andReturn(['message' => ['content' => '{"score":8,"remarks":"Addition works; subtraction needs correction."}']]);
        $grader = new class($client, new FileContentExtractorService) extends AiDocumentGraderService {
            public function checkInstructions($activity): array
            {
                return $this->evaluateDocument('Student evidence', 'output.txt', 'Activity', 'activity', 10,
                    description: $this->activityInstructions($activity), rubricContent: 'Correctness: 10 points');
            }
        };
        $this->assertSame(8.0, $grader->checkInstructions($activity)['score']);
    }

    public function test_missing_rubric_is_rejected(): void
    {
        $this->expectExceptionMessage('Attach a rubric before checking submissions.');
        $this->grader()->requireRubric(null);
    }

    public function test_unreadable_rubric_is_rejected(): void
    {
        $this->expectExceptionMessage('The attached rubric could not be read.');
        $this->grader()->evaluate(null);
    }

    public function test_offline_ai_does_not_invent_a_score(): void
    {
        $this->expectExceptionMessage('No score was changed.');
        $this->grader(null, false)->evaluate('Evidence: 10 points');
    }

    public function test_complete_feedback_is_not_truncated(): void
    {
        $feedback = str_repeat('Evidence and actionable feedback. ', 60);
        $result = $this->grader($feedback)->evaluate('Evidence: 10 points');
        $this->assertSame(trim($feedback), $result['remarks']);
        $this->assertSame(8.0, $result['score']);
    }

    public function test_empty_feedback_does_not_save_a_score(): void
    {
        $this->expectExceptionMessage('No score was changed.');
        $this->grader('')->evaluate('Evidence: 10 points');
    }

    public function test_off_topic_submission_enforces_zero_score_and_marks_remarks(): void
    {
        $client = Mockery::mock(OllamaClient::class);
        $client->shouldReceive('ping')->andReturn(['online' => true]);
        $client->shouldReceive('chat')->andReturn([
            'message' => [
                'content' => json_encode([
                    'is_on_topic' => false,
                    'topic_relevance_summary' => 'Document is an essay about basketball, not a Python program.',
                    'score' => 18, // LLM hallucinated 18, but backend must force 0.0
                    'remarks' => 'Off-topic submission: This is about basketball.',
                ]),
            ],
        ]);

        $grader = new class($client, Mockery::mock(FileContentExtractorService::class)) extends AiDocumentGraderService {
            public function evaluate(): array
            {
                return $this->evaluateDocument('Basketball rules and history...', 'essay.txt', 'Activity 1: Python Loop', 'activity', 20, rubricContent: 'Correctness: 20 pts');
            }
        };

        $result = $grader->evaluate();
        $this->assertSame(0.0, $result['score']);
        $this->assertStringContainsString('Off-Topic Submission (0.00 / 20 pts)', $result['remarks']);
        $this->assertStringContainsString('basketball', $result['remarks']);
    }

    public function test_off_topic_keyword_in_remarks_enforces_zero_score(): void
    {
        $client = Mockery::mock(OllamaClient::class);
        $client->shouldReceive('ping')->andReturn(['online' => true]);
        $client->shouldReceive('chat')->andReturn([
            'message' => [
                'content' => json_encode([
                    'is_on_topic' => true, // LLM mistakenly reported true
                    'score' => 18, // LLM hallucinated 18
                    'remarks' => 'The submission is completely different topic and unrelated to the activity.',
                ]),
            ],
        ]);

        $grader = new class($client, Mockery::mock(FileContentExtractorService::class)) extends AiDocumentGraderService {
            public function evaluate(): array
            {
                return $this->evaluateDocument('Irrelevant text', 'doc.txt', 'Activity 1', 'activity', 20, rubricContent: 'Rubric: 20 pts');
            }
        };

        $result = $grader->evaluate();
        $this->assertSame(0.0, $result['score']);
        $this->assertStringContainsString('Off-Topic Submission', $result['remarks']);
    }

    public function test_criteria_breakdown_calculates_exact_sum(): void
    {
        $client = Mockery::mock(OllamaClient::class);
        $client->shouldReceive('ping')->andReturn(['online' => true]);
        $client->shouldReceive('chat')->andReturn([
            'message' => [
                'content' => json_encode([
                    'is_on_topic' => true,
                    'criteria_breakdown' => [
                        ['criterion' => 'Logic', 'max_points' => 10, 'points_awarded' => 8, 'justification' => 'Good'],
                        ['criterion' => 'Formatting', 'max_points' => 10, 'points_awarded' => 6, 'justification' => 'Fair'],
                    ],
                    'score' => 20, // Sum is 14, backend enforces 14.0
                    'remarks' => 'Criteria evaluated: Logic 8/10, Formatting 6/10.',
                ]),
            ],
        ]);

        $grader = new class($client, Mockery::mock(FileContentExtractorService::class)) extends AiDocumentGraderService {
            public function evaluate(): array
            {
                return $this->evaluateDocument('Code content', 'main.py', 'Activity 1', 'activity', 20, rubricContent: 'Logic 10, Formatting 10');
            }
        };

        $result = $grader->evaluate();
        $this->assertSame(14.0, $result['score']);
    }

    public function test_large_document_does_not_abort_checking(): void
    {
        $client = Mockery::mock(OllamaClient::class);
        $client->shouldReceive('ping')->andReturn(['online' => true]);
        $client->shouldReceive('chat')->andReturn([
            'message' => [
                'content' => json_encode([
                    'is_on_topic' => true,
                    'score' => 19.5,
                    'remarks' => 'Comprehensive long report evaluated successfully.',
                ]),
            ],
        ]);

        $grader = new class($client, Mockery::mock(FileContentExtractorService::class)) extends AiDocumentGraderService {
            public function evaluate(): array
            {
                // Create a 25,000 character document (exceeds the old 12,000 limit)
                $longSubmission = str_repeat("Detailed analysis and experimental evaluation step by step.\n", 400);
                return $this->evaluateDocument($longSubmission, 'report.docx', 'Activity 1', 'activity', 20, rubricContent: 'Analysis: 20 pts');
            }
        };

        $result = $grader->evaluate();
        $this->assertSame(19.5, $result['score']);
        $this->assertStringContainsString('Comprehensive long report', $result['remarks']);
    }
}

