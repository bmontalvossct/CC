<?php

namespace App\Services\Autochecker;

use Exception;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    public function __construct(
        protected OllamaClient $ollamaClient
    ) {
    }

    /**
     * Evaluate a student submission against a verified structured rubric.
     *
     * @param string $content Code or text with line numbers
     * @param string $filename
     * @param float $maxPoints
     * @param array<int, array{id: string, name: string, max_points: float, description?: string}> $rubricCriteria
     * @param string|null $referenceSolution
     * @param string|null $assessmentInstructions
     * @return array{
     *     score: ?float,
     *     max_points: float,
     *     percentage: ?float,
     *     criteria_scores: array<string, array<string, mixed>>,
     *     overall_summary: string,
     *     key_strengths: array<string>,
     *     key_improvements: array<string>,
     *     model: string,
     *     raw_output: string,
     *     error: ?string
     * }
     */
    public function evaluateSubmission(
        string $content,
        string $filename,
        float $maxPoints,
        array $rubricCriteria,
        ?string $referenceSolution = null,
        ?string $assessmentInstructions = null
    ): array {
        // 1. Validate rubric criteria sum against assessment max points (within 0.01 tolerance)
        $this->validateRubric($rubricCriteria, $maxPoints);

        // 2. Select profile based on file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isCode = in_array($ext, ['py', 'java', 'c', 'cpp', 'cs', 'js', 'jsx', 'ts', 'tsx', 'php', 'sql', 'rb', 'go', 'rs', 'html', 'css'], true);
        $profile = $isCode ? 'code_grading' : 'general_grading';
        $model = $this->ollamaClient->resolveProfileModel($profile);

        // 3. Build Prompt
        $systemPrompt = $this->buildGradingSystemPrompt($isCode);
        $userPrompt = $this->buildGradingUserPrompt(
            content: $content,
            filename: $filename,
            maxPoints: $maxPoints,
            rubricCriteria: $rubricCriteria,
            referenceSolution: $referenceSolution,
            assessmentInstructions: $assessmentInstructions
        );

        $jsonSchema = $this->getGradingJsonSchema();

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        // 4. Call Ollama with schema constraint
        $attempt = 0;
        $maxAttempts = 2; // Initial + 1 repair attempt
        $lastError = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $response = $this->ollamaClient->chat(
                    profile: $profile,
                    messages: $messages,
                    tools: [],
                    schema: $jsonSchema,
                    extraOptions: ['temperature' => 0.0]
                );

                $rawContent = $response['message']['content'] ?? '';
                $parsed = json_decode($rawContent, true);

                if (! is_array($parsed) || ! isset($parsed['criterion_evaluations'])) {
                    throw new Exception("Ollama returned invalid JSON schema structure.");
                }

                // 5. Backend-enforced score computation and boundary validation
                return $this->processAndEnforceScores($parsed, $rubricCriteria, $maxPoints, $model, $rawContent);
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                Log::warning("Grading evaluation attempt {$attempt} failed: " . $lastError);

                if ($attempt === 1) {
                    // Inject repair message
                    $messages[] = [
                        'role' => 'user',
                        'content' => 'Your previous response did not match the required JSON schema. Please strictly output the JSON object with "criterion_evaluations" matching all requested rubric criteria.',
                    ];
                }
            }
        }

        // Return failed/uncomputable proposal if repair failed
        return [
            'score' => null,
            'max_points' => $maxPoints,
            'percentage' => null,
            'criteria_scores' => [],
            'overall_summary' => 'Automated evaluation failed to produce a valid schema. Please grade manually.',
            'key_strengths' => [],
            'key_improvements' => [],
            'model' => $model,
            'raw_output' => '',
            'error' => $lastError,
        ];
    }

    /**
     * Validate that rubric criterion totals equal the assessment maximum within 0.01.
     *
     * @param array<int, array{id: string, name: string, max_points: float}> $rubricCriteria
     * @param float $assessmentMaxPoints
     * @throws Exception
     */
    public function validateRubric(array $rubricCriteria, float $assessmentMaxPoints): void
    {
        if (empty($rubricCriteria)) {
            throw new Exception("Rubric must contain at least one criterion.", 422);
        }

        $rubricSum = 0.0;
        $seenIds = [];

        foreach ($rubricCriteria as $criterion) {
            $id = $criterion['id'] ?? '';
            $points = (float) ($criterion['max_points'] ?? 0);

            if (empty($id)) {
                throw new Exception("Each rubric criterion must have a unique ID.", 422);
            }

            if (in_array($id, $seenIds, true)) {
                throw new Exception("Duplicate rubric criterion ID '{$id}'.", 422);
            }

            $seenIds[] = $id;

            if ($points <= 0) {
                throw new Exception("Criterion '{$criterion['name']}' max points must be greater than 0.", 422);
            }

            $rubricSum += $points;
        }

        if (abs($rubricSum - $assessmentMaxPoints) > 0.01) {
            throw new Exception(
                "Rubric criteria total ({$rubricSum} pts) does not equal the assessment maximum points ({$assessmentMaxPoints} pts). Please auto-balance or adjust criterion points.",
                422
            );
        }
    }

    /**
     * Compute final totals strictly on the backend.
     */
    protected function processAndEnforceScores(
        array $parsed,
        array $rubricCriteria,
        float $maxPoints,
        string $model,
        string $rawContent
    ): array {
        $rubricMap = collect($rubricCriteria)->keyBy('id');
        $evaluations = $parsed['criterion_evaluations'] ?? [];
        $criteriaScores = [];
        $totalEarned = 0.0;

        foreach ($evaluations as $eval) {
            $cId = $eval['criterion_id'] ?? '';
            if (! isset($rubricMap[$cId])) {
                continue;
            }

            $criterion = $rubricMap[$cId];
            $cMax = (float) $criterion['max_points'];
            $rawScore = (float) ($eval['score'] ?? 0);

            // Clamp score between 0 and criterion max points
            $boundedScore = min($cMax, max(0.0, round($rawScore, 2)));
            $totalEarned += $boundedScore;

            $criteriaScores[$cId] = [
                'name' => $criterion['name'],
                'score' => $boundedScore,
                'max_points' => $cMax,
                'rationale' => trim((string) ($eval['rationale'] ?? '')),
                'evidence_quote' => trim((string) ($eval['evidence_quote'] ?? '')),
                'strengths' => trim((string) ($eval['strengths'] ?? '')),
                'improvements' => trim((string) ($eval['improvements'] ?? '')),
            ];
        }

        // Fill any criteria missed by the LLM with 0 and rationale
        foreach ($rubricCriteria as $c) {
            if (! isset($criteriaScores[$c['id']])) {
                $criteriaScores[$c['id']] = [
                    'name' => $c['name'],
                    'score' => 0.0,
                    'max_points' => (float) $c['max_points'],
                    'rationale' => 'Criterion was not addressed in the evaluation.',
                    'evidence_quote' => '',
                    'strengths' => '',
                    'improvements' => 'Ensure requirement is fulfilled.',
                ];
            }
        }

        $finalScore = min($maxPoints, max(0.0, round($totalEarned, 2)));
        $pct = $maxPoints > 0 ? round(($finalScore / $maxPoints) * 100, 2) : 0.0;

        return [
            'score' => $finalScore,
            'max_points' => $maxPoints,
            'percentage' => $pct,
            'criteria_scores' => $criteriaScores,
            'overall_summary' => trim((string) ($parsed['overall_summary'] ?? 'Evaluation complete.')),
            'key_strengths' => array_values(array_filter((array) ($parsed['key_strengths'] ?? []))),
            'key_improvements' => array_values(array_filter((array) ($parsed['key_improvements'] ?? []))),
            'model' => $model,
            'raw_output' => $rawContent,
            'error' => null,
        ];
    }

    /**
     * Define the strict JSON schema for Ollama structured outputs.
     */
    protected function getGradingJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'criterion_evaluations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'criterion_id' => ['type' => 'string'],
                            'score' => ['type' => 'number'],
                            'rationale' => ['type' => 'string'],
                            'evidence_quote' => ['type' => 'string'],
                            'strengths' => ['type' => 'string'],
                            'improvements' => ['type' => 'string'],
                        ],
                        'required' => ['criterion_id', 'score', 'rationale'],
                    ],
                ],
                'overall_summary' => ['type' => 'string'],
                'key_strengths' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'key_improvements' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['criterion_evaluations', 'overall_summary'],
        ];
    }

    protected function buildGradingSystemPrompt(bool $isCode): string
    {
        $domainContext = $isCode
            ? "You are an expert programming instructor and code evaluator."
            : "You are an expert academic instructor and evaluator.";

        return <<<PROMPT
{$domainContext}
Evaluate the student's submission rigorously, objectively, and constructively against each specified rubric criterion.

RULES:
1. Strict Criterion Grading: Award points per criterion bounded between 0 and each criterion's max points.
2. Evidence Grounding: Cite specific code line numbers or text excerpts in "evidence_quote" to justify point deductions or praise.
3. No Hallucinations: Do not assume code exists if it is missing.
4. Output JSON Schema: You must return strictly valid JSON matching the requested schema.
PROMPT;
    }

    protected function buildGradingUserPrompt(
        string $content,
        string $filename,
        float $maxPoints,
        array $rubricCriteria,
        ?string $referenceSolution,
        ?string $assessmentInstructions
    ): string {
        $rubricText = "RUBRIC CRITERIA (Total: {$maxPoints} pts):\n";
        foreach ($rubricCriteria as $c) {
            $rubricText .= "- ID: \"{$c['id']}\" | Name: {$c['name']} (Max: {$c['max_points']} pts): " . ($c['description'] ?? 'Standard grading') . "\n";
        }

        $instructionsText = $assessmentInstructions ? "\nTASK INSTRUCTIONS:\n{$assessmentInstructions}\n" : '';
        $refText = $referenceSolution ? "\nREFERENCE SOLUTION / SPECIFICATION:\n{$referenceSolution}\n" : '';

        return <<<PROMPT
SUBMISSION FILENAME: {$filename}
{$instructionsText}{$refText}
{$rubricText}
STUDENT SUBMISSION EVIDENCE (With Line Numbers):
```
{$content}
```

Evaluate the submission against every criterion above and return your structured JSON evaluation.
PROMPT;
    }
}
