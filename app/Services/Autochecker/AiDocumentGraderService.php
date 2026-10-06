<?php

namespace App\Services\Autochecker;

use App\Models\Assessment;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectGroupMember;
use App\Models\Section;
use App\Models\Student;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiDocumentGraderService
{
    public function __construct(
        protected OllamaClient $ollamaClient,
        protected FileContentExtractorService $extractorService,
        protected ?ModuleRagService $ragService = null,
    ) {
        $this->ragService ??= app(ModuleRagService::class);
    }

    public function activityInstructions(Assessment|Project $activity, ?string $additional = null): ?string
    {
        $instructions = trim(implode("\n\n", array_filter([$activity->description, $additional])));
        if ($activity->activity_file_path) {
            $path = $this->resolveFilePath($activity->activity_file_path);
            $extracted = $path ? $this->extractorService->extract($path, $activity->activity_file_name) : [];
            $content = trim($extracted['content'] ?? '');
            if ($content === '') {
                throw new Exception('The activity instructions file could not be read. Replace it with a readable document before checking.', 422);
            }
            $instructions .= "\n\nAttached activity instructions ({$activity->activity_file_name}):\n".$content;
        }
        if (mb_strlen($instructions) > 50000) {
            $instructions = mb_substr($instructions, 0, 50000)."\n\n[Activity instructions truncated for context budget]";
        }

        return $instructions !== '' ? $instructions : null;
    }

    public function requireRubric(?string $path): void
    {
        if (! $this->resolveFilePath($path)) {
            throw new Exception('Attach a rubric before checking submissions.', 422);
        }
    }

    public function requireRubricForActivity(Assessment|Project $activity): void
    {
        $hasFile = ! empty($activity->attachment_path) && $this->resolveFilePath($activity->attachment_path);
        $hasData = ! empty($activity->rubric_data) && is_array($activity->rubric_data) && (
            (! empty($activity->rubric_data['criteria']) && count($activity->rubric_data['criteria']) > 0) ||
            (! empty($activity->rubric_data['items']) && count($activity->rubric_data['items']) > 0)
        );

        if (! $hasFile && ! $hasData) {
            throw new Exception('Attach a rubric before checking submissions.', 422);
        }
    }

    /**
     * Resolve the absolute filesystem path for a stored relative file path.
     */
    public function resolveFilePath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }

        if (file_exists(storage_path('app/'.$path))) {
            return storage_path('app/'.$path);
        }

        if (file_exists(storage_path('app/private/'.$path))) {
            return storage_path('app/private/'.$path);
        }

        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    /**
     * Grade an individual student's attached output for an assessment.
     *
     * @return array{score: float, remarks: string, student_id: int}
     *
     * @throws Exception
     */
    public function gradeAssessmentSubmission(Section $section, Assessment $assessment, Student $student): array
    {
        $this->requireRubricForActivity($assessment);
        $score = $assessment->scores()->where('student_id', $student->id)->first();
        if (! $score || ! $score->attachment_path) {
            throw new Exception("No attached document found for {$student->full_name}.", 422);
        }

        $studentFilePath = $this->resolveFilePath($score->attachment_path);
        $extractedStudent = $studentFilePath ? $this->extractorService->extract($studentFilePath, $score->attachment_name) : ['content' => ''];

        $studentContent = $extractedStudent['content'] ?? '';
        $studentImage = $extractedStudent['image_base64'] ?? null;
        if (empty(trim($studentContent)) && empty($studentImage)) {
            throw new Exception('The student output could not be read. Review it manually.', 422);
        }

        // Extract teacher's attached activity/rubric file if present
        $rubricContent = null;
        $rubricImage = null;
        $rubricFilePath = $this->resolveFilePath($assessment->attachment_path);
        if ($rubricFilePath) {
            $extractedRubric = $this->extractorService->extract($rubricFilePath, $assessment->attachment_name);
            if (! empty(trim($extractedRubric['content'] ?? ''))) {
                $rubricContent = $extractedRubric['content'];
            }
            if (! empty($extractedRubric['image_base64'])) {
                $rubricImage = $extractedRubric['image_base64'];
            }
        }

        $studentName = $student->last_name.', '.$student->first_name.($student->middle_name ? ' '.$student->middle_name : '');
        $maxPoints = (float) $assessment->max_points;

        $evaluation = $this->evaluateDocument(
            submissionContent: $studentContent,
            submissionFilename: $score->attachment_name ?: 'submission.txt',
            title: $assessment->title,
            type: $assessment->type ?: 'activity',
            maxPoints: $maxPoints,
            description: $this->activityInstructions($assessment),
            rubricContent: $rubricContent,
            rubricFilename: $assessment->attachment_name,
            context: [
                'Student' => $studentName,
                'Student ID' => $student->student_number,
                'Section' => $section->name,
                'Subject' => $section->subject_code.' - '.$section->subject_title,
            ],
            rubricType: $assessment->rubric_type,
            rubricData: $assessment->rubric_data,
            submissionImage: $studentImage,
            rubricImage: $rubricImage,
            section: $section
        );

        return [
            'score' => $evaluation['score'],
            'remarks' => $evaluation['remarks'],
            'student_id' => $student->id,
        ];
    }

    /**
     * Grade a project group's attached output.
     *
     * @return array{score: float, remarks: string, group_id: int}
     *
     * @throws Exception
     */
    public function gradeProjectGroupSubmission(Section $section, Project $project, ProjectGroup $group): array
    {
        $this->requireRubricForActivity($project);
        if (! $group->attachment_path) {
            throw new Exception("No attached document found for {$group->name}.", 422);
        }

        $groupFilePath = $this->resolveFilePath($group->attachment_path);
        $extractedGroup = $groupFilePath ? $this->extractorService->extract($groupFilePath, $group->attachment_name) : ['content' => ''];

        $groupContent = $extractedGroup['content'] ?? '';
        $groupImage = $extractedGroup['image_base64'] ?? null;
        if (empty(trim($groupContent)) && empty($groupImage)) {
            throw new Exception('The output could not be read. Review it manually.', 422);
        }

        // Extract teacher's attached project/rubric file if present
        $rubricContent = null;
        $rubricImage = null;
        $rubricFilePath = $this->resolveFilePath($project->attachment_path);
        if ($rubricFilePath) {
            $extractedRubric = $this->extractorService->extract($rubricFilePath, $project->attachment_name);
            if (! empty(trim($extractedRubric['content'] ?? ''))) {
                $rubricContent = $extractedRubric['content'];
            }
            if (! empty($extractedRubric['image_base64'])) {
                $rubricImage = $extractedRubric['image_base64'];
            }
        }

        $maxPoints = (float) ($project->max_points ?: 100);

        $evaluation = $this->evaluateDocument(
            submissionContent: $groupContent,
            submissionFilename: $group->attachment_name ?: 'group_report.txt',
            title: $project->title,
            type: $project->type ?: 'reporting',
            maxPoints: $maxPoints,
            description: $this->activityInstructions($project),
            rubricContent: $rubricContent,
            rubricFilename: $project->attachment_name,
            context: [
                'Group' => $group->name,
                'Topic' => $group->topic,
                'Topic Description' => $group->description,
                'Section' => $section->name,
            ],
            rubricType: $project->rubric_type,
            rubricData: $project->rubric_data,
            submissionImage: $groupImage,
            rubricImage: $rubricImage,
            section: $section
        );

        return [
            'score' => $evaluation['score'],
            'remarks' => $evaluation['remarks'],
            'group_id' => $group->id,
        ];
    }

    /**
     * Grade an individual project member's attached output.
     *
     * @return array{score: float, remarks: string, student_id: int}
     *
     * @throws Exception
     */
    public function gradeProjectMemberSubmission(Section $section, Project $project, ProjectGroup $group, Student $student): array
    {
        $this->requireRubricForActivity($project);
        $member = ProjectGroupMember::where('project_group_id', $group->id)
            ->where('student_id', $student->id)
            ->first();

        if (! $member || ! $member->attachment_path) {
            throw new Exception("No attached document found for {$student->full_name}.", 422);
        }

        $memberFilePath = $this->resolveFilePath($member->attachment_path);
        $extractedMember = $memberFilePath ? $this->extractorService->extract($memberFilePath, $member->attachment_name) : ['content' => ''];

        $memberContent = $extractedMember['content'] ?? '';
        $memberImage = $extractedMember['image_base64'] ?? null;
        if (empty(trim($memberContent)) && empty($memberImage)) {
            throw new Exception('The output could not be read. Review it manually.', 422);
        }

        $rubricContent = null;
        $rubricImage = null;
        $rubricFilePath = $this->resolveFilePath($project->attachment_path);
        if ($rubricFilePath) {
            $extractedRubric = $this->extractorService->extract($rubricFilePath, $project->attachment_name);
            if (! empty(trim($extractedRubric['content'] ?? ''))) {
                $rubricContent = $extractedRubric['content'];
            }
            if (! empty($extractedRubric['image_base64'])) {
                $rubricImage = $extractedRubric['image_base64'];
            }
        }

        $studentName = $student->last_name.', '.$student->first_name.($student->middle_name ? ' '.$student->middle_name : '');
        $maxPoints = (float) ($project->max_points ?: 100);

        $evaluation = $this->evaluateDocument(
            submissionContent: $memberContent,
            submissionFilename: $member->attachment_name ?: 'member_report.txt',
            title: $project->title,
            type: $project->type ?: 'reporting',
            maxPoints: $maxPoints,
            description: $this->activityInstructions($project),
            rubricContent: $rubricContent,
            rubricFilename: $project->attachment_name,
            context: [
                'Presenter' => $studentName,
                'Group' => $group->name,
                'Topic' => $group->topic,
                'Role' => $member->role,
                'Section' => $section->name,
            ],
            rubricType: $project->rubric_type,
            rubricData: $project->rubric_data,
            submissionImage: $memberImage,
            rubricImage: $rubricImage,
            section: $section
        );

        return [
            'score' => $evaluation['score'],
            'remarks' => $evaluation['remarks'],
            'student_id' => $student->id,
        ];
    }

    /**
     * Study an uploaded rubric file or raw rubric text with Octo AI to extract structured criteria or answer keys.
     *
     * @return array{mode: string, title?: string, summary: string, criteria?: array, items?: array}
     *
     * @throws Exception
     */
    public function studyRubricDocument(?string $filePath, ?string $fileName, float $maxPoints, ?string $rawText = null): array
    {
        $content = '';
        $imagePayload = null;
        if ($filePath && file_exists($filePath)) {
            $extracted = $this->extractorService->extract($filePath, $fileName);
            $content = trim($extracted['content'] ?? '');
            $imagePayload = $extracted['image_base64'] ?? null;
        } elseif ($rawText) {
            $content = trim($rawText);
        }

        if (empty($content) && empty($imagePayload)) {
            throw new Exception('The rubric document could not be read or is empty. Please upload a readable file or enter instructions.', 422);
        }

        $ping = $this->ollamaClient->ping();
        if (! $ping['online']) {
            throw new Exception('Octo AI is currently offline. Please ensure Ollama is running to study rubrics automatically.', 422);
        }

        $systemPrompt = <<<PROMPT
You are Octo, an expert educational assistant specializing in curriculum design, assessment rubrics, and automated grading schema extraction.
Your task is to analyze the provided teacher document, exam, quiz, assignment, or rubric sheet and convert it into one of two structured grading modes:

MODE 1: "answer_key"
Use this mode if the document consists of specific questions/items with expected answers, test cases, or solutions (e.g. multiple choice, fill in the blanks, short answer, coding problem test outputs, numerical questions).
Extract each question item with:
- "item_number" (integer 1, 2, 3...)
- "question" (the question prompt or problem statement)
- "correct_answer" (the correct solution, expected answer, or key)
- "points" (points allocated to this question, scaling proportionally to sum to {$maxPoints})
- "percentage" (percentage of total score, e.g. 10.0%)
- "case_sensitive" (boolean, default false)
- "explanation" (short explanation or notes)

MODE 2: "percentage"
Use this mode if the document is an analytical/holistic rubric, grading criteria breakdown, or project requirements (e.g. Correctness 40%, Code Style 30%, Documentation 30%, or criterion levels).
Extract each criterion with:
- "id" (string, e.g. "crit_1", "crit_2")
- "name" (title of criterion)
- "percentage" (weight percentage, e.g. 40) - ALL CRITERIA PERCENTAGES MUST SUM TO 100
- "max_points" (calculated as percentage% * {$maxPoints})
- "description" (scoring criteria details and guidelines)

Choose the best matching mode based on the document contents. Ensure that total points equal {$maxPoints} and total percentages equal 100.
PROMPT;

        $jsonSchema = [
            'type' => 'object',
            'properties' => [
                'mode' => [
                    'type' => 'string',
                    'enum' => ['percentage', 'answer_key'],
                    'description' => 'The detected rubric structure mode',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Short title or summary of the rubric/exam',
                ],
                'summary' => [
                    'type' => 'string',
                    'description' => 'Octo analysis summary of the studied document',
                ],
                'criteria' => [
                    'type' => 'array',
                    'description' => 'For percentage mode: list of weighted grading criteria',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'percentage' => ['type' => 'number'],
                            'max_points' => ['type' => 'number'],
                            'description' => ['type' => 'string'],
                        ],
                        'required' => ['name', 'percentage', 'max_points'],
                    ],
                ],
                'items' => [
                    'type' => 'array',
                    'description' => 'For answer_key mode: itemized question-by-question answer key',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'item_number' => ['type' => 'integer'],
                            'question' => ['type' => 'string'],
                            'correct_answer' => ['type' => 'string'],
                            'points' => ['type' => 'number'],
                            'percentage' => ['type' => 'number'],
                            'case_sensitive' => ['type' => 'boolean'],
                            'explanation' => ['type' => 'string'],
                        ],
                        'required' => ['item_number', 'correct_answer', 'points'],
                    ],
                ],
            ],
            'required' => ['mode', 'summary'],
        ];

        // Truncate to budget if needed
        $truncated = mb_substr($content, 0, 45000);

        try {
            $userMsg = [
                'role' => 'user',
                'content' => "Document Name: {$fileName}\nMax Points: {$maxPoints} pts\n\n--- DOCUMENT CONTENT ---\n{$truncated}",
            ];
            if (! empty($imagePayload)) {
                $userMsg['images'] = [$imagePayload];
            }

            $profile = ! empty($imagePayload) ? 'vision_grading' : 'general_grading';

            $response = $this->ollamaClient->chat(
                profile: $profile,
                messages: [
                    ['role' => 'system', 'content' => $systemPrompt],
                    $userMsg,
                ],
                tools: [],
                schema: $jsonSchema,
                extraOptions: ['temperature' => 0.1]
            );

            $raw = $response['message']['content'] ?? '';
            $parsed = json_decode($raw, true);

            if (is_array($parsed) && ! empty($parsed['mode'])) {
                return $this->normalizeStudiedRubric($parsed, $maxPoints);
            }
        } catch (Exception $e) {
            Log::warning('Octo study rubric failed: '.$e->getMessage());
        }

        throw new Exception('Octo was unable to parse a structured rubric from this file. You can enter criteria or answer keys manually.', 422);
    }

    /**
     * Normalize studied rubric payload into balanced percentage criteria or answer keys.
     */
    protected function normalizeStudiedRubric(array $parsed, float $maxPoints): array
    {
        $mode = $parsed['mode'] === 'answer_key' ? 'answer_key' : 'percentage';
        $summary = $parsed['summary'] ?? 'Studied rubric document';
        $title = $parsed['title'] ?? 'Rubric Extraction';

        if ($mode === 'answer_key') {
            $rawItems = is_array($parsed['items'] ?? null) ? $parsed['items'] : [];
            $items = [];
            $totalPts = 0.0;

            foreach ($rawItems as $idx => $item) {
                $num = (int) ($item['item_number'] ?? ($idx + 1));
                $q = trim((string) ($item['question'] ?? "Question {$num}"));
                $ans = trim((string) ($item['correct_answer'] ?? ''));
                $pts = max(0.01, (float) ($item['points'] ?? 1.0));
                $totalPts += $pts;

                $items[] = [
                    'id' => 'item_'.($idx + 1).'_'.substr(md5($q.$ans), 0, 6),
                    'item_number' => $num,
                    'question' => $q,
                    'correct_answer' => $ans,
                    'points' => $pts,
                    'percentage' => round(($pts / ($maxPoints > 0 ? $maxPoints : 100)) * 100, 2),
                    'case_sensitive' => (bool) ($item['case_sensitive'] ?? false),
                    'explanation' => trim((string) ($item['explanation'] ?? '')),
                ];
            }

            if (empty($items)) {
                $items[] = [
                    'id' => 'item_1',
                    'item_number' => 1,
                    'question' => 'Item 1',
                    'correct_answer' => '',
                    'points' => $maxPoints,
                    'percentage' => 100.0,
                    'case_sensitive' => false,
                    'explanation' => '',
                ];
            } elseif ($totalPts > 0 && abs($totalPts - $maxPoints) > 0.01) {
                // Pro-rate points to sum to maxPoints
                $runningSum = 0.0;
                $count = count($items);
                foreach ($items as $idx => &$it) {
                    if ($idx === $count - 1) {
                        $it['points'] = round($maxPoints - $runningSum, 2);
                    } else {
                        $it['points'] = round(($it['points'] / $totalPts) * $maxPoints, 2);
                        $runningSum += $it['points'];
                    }
                    $it['percentage'] = round(($it['points'] / $maxPoints) * 100, 2);
                }
            }

            return [
                'mode' => 'answer_key',
                'title' => $title,
                'summary' => $summary,
                'items' => $items,
            ];
        }

        // Percentage Mode
        $rawCriteria = is_array($parsed['criteria'] ?? null) ? $parsed['criteria'] : [];
        $criteria = [];
        $totalPct = 0.0;

        foreach ($rawCriteria as $idx => $c) {
            $name = trim((string) ($c['name'] ?? 'Criterion '.($idx + 1)));
            $pct = max(1.0, (float) ($c['percentage'] ?? 25.0));
            $totalPct += $pct;

            $criteria[] = [
                'id' => 'crit_'.($idx + 1).'_'.substr(md5($name), 0, 6),
                'name' => $name,
                'percentage' => $pct,
                'max_points' => round(($pct / 100) * $maxPoints, 2),
                'description' => trim((string) ($c['description'] ?? '')),
            ];
        }

        if (empty($criteria)) {
            $criteria = [
                ['id' => 'crit_1', 'name' => 'Correctness & Logic', 'percentage' => 50, 'max_points' => round(0.50 * $maxPoints, 2), 'description' => 'Satisfies core functional requirements'],
                ['id' => 'crit_2', 'name' => 'Structure & Quality', 'percentage' => 30, 'max_points' => round(0.30 * $maxPoints, 2), 'description' => 'Clean design, syntax, and organization'],
                ['id' => 'crit_3', 'name' => 'Documentation', 'percentage' => 20, 'max_points' => round(0.20 * $maxPoints, 2), 'description' => 'Explanatory notes and documentation'],
            ];
        } elseif ($totalPct > 0 && abs($totalPct - 100.0) > 0.01) {
            // Normalize percentages to 100%
            $runningPct = 0.0;
            $runningPts = 0.0;
            $count = count($criteria);
            foreach ($criteria as $idx => &$crit) {
                if ($idx === $count - 1) {
                    $crit['percentage'] = round(100.0 - $runningPct, 2);
                    $crit['max_points'] = round($maxPoints - $runningPts, 2);
                } else {
                    $crit['percentage'] = round(($crit['percentage'] / $totalPct) * 100, 2);
                    $crit['max_points'] = round(($crit['percentage'] / 100) * $maxPoints, 2);
                    $runningPct += $crit['percentage'];
                    $runningPts += $crit['max_points'];
                }
            }
        }

        return [
            'mode' => 'percentage',
            'title' => $title,
            'summary' => $summary,
            'criteria' => $criteria,
        ];
    }

    /**
     * Send prompt to Ollama with rubric matching and return validated score & remarks.
     *
     * @return array{score: float, remarks: string}
     */
    protected function evaluateDocument(
        string $submissionContent,
        string $submissionFilename,
        string $title,
        string $type,
        float $maxPoints,
        ?string $description = null,
        ?string $rubricContent = null,
        ?string $rubricFilename = null,
        array $context = [],
        ?string $rubricType = null,
        ?array $rubricData = null,
        ?string $submissionImage = null,
        ?string $rubricImage = null,
        ?Section $section = null
    ): array {
        $hasRubricData = ! empty($rubricData) && (
            (! empty($rubricData['criteria']) && count($rubricData['criteria']) > 0) ||
            (! empty($rubricData['items']) && count($rubricData['items']) > 0)
        );

        if ((! $rubricContent || trim($rubricContent) === '') && ! $hasRubricData && empty($rubricImage)) {
            throw new Exception('The attached rubric could not be read. Attach a readable rubric before checking.', 422);
        }

        // Support large documents up to 50,000 characters (~12,500 tokens), gracefully truncating if larger so grading never fails
        $maxBudget = 50000;
        if (mb_strlen($submissionContent) > $maxBudget) {
            $truncatedSubmission = mb_substr($submissionContent, 0, 45000)."\n\n[... Submission content truncated for AI context budget ...]\n\n".mb_substr($submissionContent, -5000);
        } else {
            $truncatedSubmission = $submissionContent;
        }

        if ($rubricContent && mb_strlen($rubricContent) > $maxBudget) {
            $truncatedRubric = mb_substr($rubricContent, 0, $maxBudget)."\n\n[... Rubric content truncated for AI context budget ...]";
        } else {
            $truncatedRubric = $rubricContent;
        }

        $ext = strtolower(pathinfo($submissionFilename, PATHINFO_EXTENSION));
        $isCode = in_array($ext, ['py', 'java', 'c', 'cpp', 'cs', 'js', 'jsx', 'ts', 'tsx', 'php', 'sql', 'html', 'css'], true);
        $hasImage = (! empty($submissionImage) || ! empty($rubricImage));
        $profile = $hasImage ? 'vision_grading' : ($isCode ? 'code_grading' : 'general_grading');

        // Check if Ollama is available
        $ping = $this->ollamaClient->ping();
        if (! $ping['online']) {
            throw new Exception('AI checking is unavailable or returned incomplete feedback. No score was changed. Please retry.', 422);
        }

        $systemPrompt = <<<PROMPT
You are an expert, meticulous academic evaluator and grader. Your duty is to rigorously evaluate a student submission against the specified activity requirements, instructions, and rubrics.

CRITICAL EVALUATION PROTOCOL:

1. BENCHMARK AUTHORITY (Teacher Specifications, Rubrics & Course Modules Are Absolute):
- The grading criteria, percentage weights, expected answers, and rubric rules MUST ALWAYS originate solely from the TEACHER'S ATTACHED ACTIVITY FILE, SPECIFICATIONS, CONFIGURED RUBRIC DATA, AND TAUGHT COURSE MODULES.
- The student's attached file is strictly the student submission to be evaluated against those criteria.
- The student's submission must NEVER dictate, alter, or replace what the rubric criteria or percentage distribution is.
- Verify technical correctness, concepts, and formulas against the definitions and examples taught in the grounded course modules.

2. TOPIC & RELEVANCE GATE (Zero Tolerance for Off-Topic Work):
- The FIRST check is whether the student submission genuinely addresses the required activity topic, prompt, instructions, and rubrics.
- If the submission is OFF-TOPIC, IRRELEVANT, AN UNRELATED TOPIC/ASSIGNMENT, PLACEHOLDER, NONSENSICAL, BLANK, OR DOES NOT ATTEMPT THE SPECIFIED TASK:
  * You MUST set "is_on_topic" to false.
  * You MUST set "score" to 0.00.
  * You MUST NOT award points for writing style, formatting, grammar, structure, or effort if the content does not address the required activity topic.
  * In "remarks", state clearly and unambiguously: "Off-Topic Submission (0.00 / {$maxPoints} pts): The submitted work does not address the required activity topic/instructions. [Explain what was required vs what was submitted]. Score: 0.00 / {$maxPoints} pts."

3. OBJECTIVE RUBRIC-ALIGNED EVALUATION (When On-Topic):
- Evaluate strictly against each rubric criterion and specific requirement outlined in the teacher's activity specifications.
- Every awarded point must be substantiated by tangible evidence in the student's submission.
- If a required question, feature, analysis, or criterion is missing, incomplete, or incorrect, deduct points proportionally.
- NEVER invent, assume, or hallucinate content that is not explicitly present in the submission.

4. SCORE & REMARKS CONSISTENCY:
- The "score" must mathematically match the sum of points earned across all rubric criteria (from 0.00 to {$maxPoints}).
- In "remarks", provide a transparent, itemized breakdown for each rubric criterion showing:
  * [Criterion Name] (Earned / Possible pts): Justification citing specific evidence or noting omissions.
  * Summary: Overall performance, specific deductions applied, and actionable advice for improvement.
PROMPT;

        $contextBlock = '';
        foreach ($context as $k => $v) {
            if ($v) {
                $contextBlock .= "{$k}: {$v}\n";
            }
        }

        $rubricBlock = '';
        if ($description) {
            $rubricBlock .= "Teacher Instructions & Notes:\n{$description}\n\n";
        }

        if (! empty($rubricData)) {
            $mode = $rubricData['mode'] ?? $rubricType ?? 'percentage';
            if ($mode === 'answer_key' && ! empty($rubricData['items'])) {
                $rubricBlock .= "--- OFFICIAL ITEM-BY-ITEM EXACT ANSWER KEY ---\n";
                $rubricBlock .= "STRICT ANSWER KEY MATCHING RULES:\n";
                $rubricBlock .= "- The student's answer for each question must match the Expected Answer EXACTLY.\n";
                $rubricBlock .= "- If [Case-Sensitive] is specified, capitalization must match character-by-character.\n";
                $rubricBlock .= "- If not case-sensitive, case is ignored, but spelling, words, and exact content must match.\n";
                $rubricBlock .= "- If the student answered with a wrong choice, misspelled word, synonym, or incorrect value, award 0 points for that item.\n";
                $rubricBlock .= "- No partial credit is allowed for incorrect answers on an answer key.\n\n";
                foreach ($rubricData['items'] as $item) {
                    $num = $item['item_number'] ?? 1;
                    $q = ! empty($item['question']) ? "Q: {$item['question']} | " : '';
                    $ans = $item['expected_answer'] ?? $item['correct_answer'] ?? $item['answer'] ?? '';
                    $pts = $item['points'] ?? 0;
                    $cs = ! empty($item['case_sensitive']) ? ' [Case-Sensitive]' : '';
                    $rubricBlock .= "Item #{$num}: {$q}Expected Answer: \"{$ans}\" ({$pts} pts){$cs}\n";
                }
                $rubricBlock .= "\n";
            } elseif (! empty($rubricData['criteria'])) {
                $rubricBlock .= "--- STRUCTURED PERCENTAGE RATE RUBRICS (Total 100%) ---\n";
                foreach ($rubricData['criteria'] as $c) {
                    $name = $c['name'] ?? 'Criterion';
                    $pct = $c['percentage'] ?? 0;
                    $pts = $c['max_points'] ?? round(($pct / 100) * $maxPoints, 2);
                    $desc = ! empty($c['description']) ? " - {$c['description']}" : '';
                    $rubricBlock .= "- {$name} ({$pct}% / {$pts} pts){$desc}\n";
                }
                $rubricBlock .= "\n";
            }
        }

        if ($truncatedRubric) {
            $rubricBlock .= "Attached Activity / Rubric Document ({$rubricFilename}):\n{$truncatedRubric}\n\n";
        }

        if (empty(trim($rubricBlock))) {
            $rubricBlock = "Standard grading criteria: Completeness, accuracy, structure, clarity, and quality of work.\n\n";
        }

        $mode = (! empty($rubricData['mode']) ? $rubricData['mode'] : ($rubricType ?? 'percentage'));
        if ($mode === 'answer_key' && ! empty($rubricData['items'])) {
            $instructionsText = <<<'INSTR'
ANSWER KEY EXTRACTION INSTRUCTIONS:
1. Examine the student submission against the official Answer Key above.
2. Check if the submission is genuinely attempting this quiz/assignment. If off-topic, unrelated, placeholder, or blank, set "is_on_topic": false and "score": 0.
3. For each Item in the Answer Key (#1..N), extract what the student wrote in "student_answer". If not answered or missing in the document, write "None found".
4. Record your extracted answers in "criteria_breakdown" for each item.
INSTR;
        } else {
            $instructionsText = <<<INSTR
GRADING INSTRUCTIONS:
1. Examine the student submission against the Activity Specifications and Rubrics above.
2. Determine if the submission is ON-TOPIC for this specific activity. If it is off-topic, unrelated, or a generic placeholder, set "is_on_topic": false and "score": 0.
3. If on-topic, evaluate each criterion in the rubrics, calculate the exact points earned (0 to {$maxPoints}), and provide clear justification for each criterion in criteria_breakdown.
INSTR;
        }

        $moduleGroundingBlock = '';
        if ($section) {
            try {
                $ragQuery = trim("{$title} ".($description ?? ''));
                $moduleChunks = $this->ragService->search($section, $ragQuery, limit: 3);
                if (! empty($moduleChunks)) {
                    $moduleGroundingBlock = $this->ragService->formatGroundingContext($moduleChunks, 3500)."\n\n";
                }
            } catch (Exception $e) {
                Log::info('Module RAG grounding skipped in grading: '.$e->getMessage());
            }
        }

        $userPrompt = <<<PROMPT
Activity Title: {$title} ({$type})
Total Max Score: {$maxPoints} pts
{$contextBlock}
--- ACTIVITY SPECIFICATIONS & RUBRICS ---
{$rubricBlock}
{$moduleGroundingBlock}--- STUDENT SUBMISSION ({$submissionFilename}) ---
{$truncatedSubmission}
---
{$instructionsText}
PROMPT;

        $images = [];
        $imageNote = '';
        if (! empty($submissionImage)) {
            $images[] = $submissionImage;
            $imageNote .= "\n[Attached Student Image / Visual Output attached for visual inspection]";
        }
        if (! empty($rubricImage)) {
            $images[] = $rubricImage;
            $imageNote .= "\n[Attached Rubric / Answer Key Image attached for visual inspection]";
        }
        if (! empty($imageNote)) {
            $userPrompt .= "\n\n--- ATTACHED VISUAL ASSETS ---".$imageNote."\nPlease inspect the attached image(s) carefully to evaluate visual outputs, screenshots, diagrams, terminal outputs, or written answers.";
        }

        $jsonSchema = [
            'type' => 'object',
            'properties' => [
                'is_on_topic' => [
                    'type' => 'boolean',
                    'description' => 'True if the submission genuinely attempts and addresses the assigned activity topic and requirements. False if off-topic, placeholder, irrelevant, or an unrelated document.',
                ],
                'topic_relevance_summary' => [
                    'type' => 'string',
                    'description' => 'Brief explanation of how the submission relates to the required activity, or why it is off-topic.',
                ],
                'criteria_breakdown' => [
                    'type' => 'array',
                    'description' => 'Breakdown of evaluation per rubric criterion or answer key item',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'criterion' => ['type' => 'string', 'description' => 'Name or Item # of criterion'],
                            'max_points' => ['type' => 'number', 'description' => 'Maximum points for this criterion / item'],
                            'points_awarded' => ['type' => 'number', 'description' => 'Points awarded (0 if incorrect or off-topic)'],
                            'student_answer' => ['type' => 'string', 'description' => 'The exact answer submitted by the student, or "None found"'],
                            'expected_answer' => ['type' => 'string', 'description' => 'The expected answer from the answer key'],
                            'is_correct' => ['type' => 'boolean', 'description' => 'True if student answer matches expected answer exactly, false otherwise'],
                            'evidence' => ['type' => 'string', 'description' => 'Specific quote or evidence from submission, or "None found"'],
                            'justification' => ['type' => 'string', 'description' => 'Why points were awarded or deducted'],
                        ],
                        'required' => ['criterion', 'max_points', 'points_awarded', 'justification'],
                    ],
                ],
                'score' => [
                    'type' => 'number',
                    'description' => "Calculated score out of {$maxPoints} (MUST be 0 if is_on_topic is false)",
                ],
                'remarks' => [
                    'type' => 'string',
                    'description' => 'Complete feedback covering rubric criteria / answer key items, evidence, deductions, strengths, and next steps',
                ],
            ],
            'required' => ['is_on_topic', 'score', 'remarks'],
        ];

        // Deterministic topic relevance verification
        $topicValidation = $this->validateTopicRelevance(
            activityText: $rubricBlock."\n".($description ?? ''),
            submissionText: $submissionContent,
            activityTitle: $title
        );

        if (! $topicValidation['is_on_topic']) {
            $topicSummary = $topicValidation['reason'] ?? 'The submitted document does not address the required activity specifications.';

            if ($mode === 'answer_key' && ! empty($rubricData['items'])) {
                return $this->evaluateAnswerKeyRubric(
                    parsed: ['criteria_breakdown' => [], 'score' => 0.0, 'remarks' => 'Off-topic submission.'],
                    rubricData: $rubricData,
                    maxPoints: $maxPoints,
                    isOnTopic: false,
                    topicSummary: $topicSummary
                );
            }

            if ($mode === 'percentage' && ! empty($rubricData['criteria'])) {
                return $this->evaluatePercentageRubric(
                    parsed: ['criteria_breakdown' => [], 'score' => 0.0, 'remarks' => 'Off-Topic Submission.'],
                    rubricData: $rubricData,
                    maxPoints: $maxPoints,
                    isOnTopic: false,
                    topicSummary: $topicSummary
                );
            }

            $ptsLabel = (floor($maxPoints) == $maxPoints ? (int) $maxPoints : number_format($maxPoints, 2)).' pts';

            return [
                'score' => 0.0,
                'remarks' => "Off-Topic Submission (0.00 / {$ptsLabel})\n\n{$topicSummary}",
            ];
        }

        try {
            $userMsg = ['role' => 'user', 'content' => $userPrompt];
            if (! empty($images)) {
                $userMsg['images'] = $images;
            }

            $response = $this->ollamaClient->chat(
                profile: $profile,
                messages: [
                    ['role' => 'system', 'content' => $systemPrompt],
                    $userMsg,
                ],
                tools: [],
                schema: $jsonSchema,
                extraOptions: ['temperature' => 0.1]
            );

            $raw = $response['message']['content'] ?? '';
            $parsed = json_decode($raw, true);

            if (
                is_array($parsed) &&
                is_numeric($parsed['score'] ?? null) &&
                is_string($parsed['remarks'] ?? null) &&
                trim($parsed['remarks']) !== '' &&
                mb_strlen($parsed['remarks']) <= 10000 &&
                ($response['done_reason'] ?? 'stop') !== 'length' &&
                (float) $parsed['score'] >= 0 &&
                (float) $parsed['score'] <= $maxPoints
            ) {
                $rawScore = round((float) $parsed['score'], 2);
                $rawRemarks = trim($parsed['remarks']);
                $isOnTopic = isset($parsed['is_on_topic']) ? (bool) $parsed['is_on_topic'] : true;
                $topicSummary = trim((string) ($parsed['topic_relevance_summary'] ?? ''));

                // Safety check: detect textual indicators of off-topic or irrelevant submission
                $lowerText = strtolower($rawRemarks.' '.$topicSummary);
                $textSaysOffTopic = str_contains($lowerText, 'off-topic')
                    || str_contains($lowerText, 'off topic')
                    || str_contains($lowerText, 'unrelated to the activity')
                    || str_contains($lowerText, 'unrelated document')
                    || str_contains($lowerText, 'does not address the required activity')
                    || str_contains($lowerText, 'does not address the assigned topic')
                    || str_contains($lowerText, 'completely different topic');

                if ($textSaysOffTopic) {
                    $isOnTopic = false;
                }

                // If configured with structured answer key items, use deterministic answer key evaluator
                if ($mode === 'answer_key' && ! empty($rubricData['items'])) {
                    return $this->evaluateAnswerKeyRubric(
                        parsed: $parsed,
                        rubricData: $rubricData,
                        maxPoints: $maxPoints,
                        isOnTopic: $isOnTopic,
                        topicSummary: $topicSummary
                    );
                }

                // If configured with structured percentage criteria, use deterministic percentage evaluator
                if ($mode === 'percentage' && ! empty($rubricData['criteria'])) {
                    return $this->evaluatePercentageRubric(
                        parsed: $parsed,
                        rubricData: $rubricData,
                        maxPoints: $maxPoints,
                        isOnTopic: $isOnTopic,
                        topicSummary: $topicSummary
                    );
                }

                // Fallback for file-based or open-ended rubrics
                if (! $isOnTopic) {
                    $score = 0.0;
                    $prefix = "Off-Topic Submission (0.00 / {$maxPoints} pts)";
                    if (! str_contains($rawRemarks, 'Off-Topic Submission')) {
                        $reason = $topicSummary ?: 'The submitted document does not address the required activity specifications or rubrics.';
                        $remarks = "{$prefix}\n\n{$reason}\n\n{$rawRemarks}";
                    } else {
                        $remarks = $rawRemarks;
                    }
                } else {
                    if (! empty($parsed['criteria_breakdown']) && is_array($parsed['criteria_breakdown'])) {
                        $sum = 0.0;
                        $validCount = 0;
                        foreach ($parsed['criteria_breakdown'] as $c) {
                            if (isset($c['points_awarded']) && is_numeric($c['points_awarded'])) {
                                $cMax = isset($c['max_points']) && is_numeric($c['max_points']) ? (float) $c['max_points'] : null;
                                $pts = (float) $c['points_awarded'];
                                if ($cMax !== null && $cMax > 0) {
                                    $pts = min($cMax, max(0.0, $pts));
                                }
                                $sum += $pts;
                                $validCount++;
                            }
                        }
                        $score = $validCount > 0 ? round(min($maxPoints, max(0.0, $sum)), 2) : min($maxPoints, max(0.0, $rawScore));
                    } else {
                        $score = min($maxPoints, max(0.0, $rawScore));
                    }
                    $remarks = $rawRemarks;
                }

                return [
                    'score' => $score,
                    'remarks' => $remarks,
                ];
            }
        } catch (Exception $e) {
            Log::warning('AI document checking failed with Ollama: '.$e->getMessage());
        }

        throw new Exception('AI checking is unavailable or returned incomplete feedback. No score was changed. Please retry.', 422);
    }

    /**
     * Deterministically evaluate and format itemized answer keys.
     *
     * @return array{score: float, remarks: string}
     */
    protected function evaluateAnswerKeyRubric(
        array $parsed,
        array $rubricData,
        float $maxPoints,
        bool $isOnTopic,
        string $topicSummary
    ): array {
        $items = $rubricData['items'] ?? [];
        if (empty($items)) {
            return [
                'score' => 0.0,
                'remarks' => 'No answer key items configured.',
            ];
        }

        $rawBreakdown = $parsed['criteria_breakdown'] ?? $parsed['items'] ?? [];
        if (! is_array($rawBreakdown)) {
            $rawBreakdown = [];
        }

        $itemLines = [];
        $totalAwarded = 0.0;
        $correctCount = 0;
        $totalItems = count($items);

        foreach ($items as $idx => $item) {
            $itemNum = (int) ($item['item_number'] ?? ($idx + 1));
            $expected = trim((string) ($item['correct_answer'] ?? $item['expected_answer'] ?? $item['answer'] ?? ''));
            $itemMax = max(0.0, (float) ($item['points'] ?? 1.0));
            $caseSensitive = ! empty($item['case_sensitive']);

            // Find matching item from AI extraction
            $extracted = null;
            foreach ($rawBreakdown as $b) {
                if (! is_array($b)) {
                    continue;
                }
                $bNum = null;
                if (isset($b['item_number'])) {
                    $bNum = (int) $b['item_number'];
                } elseif (isset($b['criterion']) && preg_match('/\b(?:item|q|question)?\s*#?\s*(\d+)\b/i', (string) $b['criterion'], $m)) {
                    $bNum = (int) $m[1];
                }
                if ($bNum === $itemNum) {
                    $extracted = $b;
                    break;
                }
            }
            if (! $extracted && isset($rawBreakdown[$idx]) && is_array($rawBreakdown[$idx])) {
                $extracted = $rawBreakdown[$idx];
            }

            $rawStudentAns = trim((string) ($extracted['student_answer'] ?? $extracted['evidence'] ?? ''));
            $isMissing = ($rawStudentAns === ''
                || strcasecmp($rawStudentAns, 'None found') === 0
                || strcasecmp($rawStudentAns, 'None') === 0
                || strcasecmp($rawStudentAns, 'Not found') === 0
                || strcasecmp($rawStudentAns, 'N/A') === 0);

            if (! $isOnTopic) {
                $studentDisplay = (! $isMissing) ? $rawStudentAns : 'Off-topic / Unrelated submission';
                $isCorrect = false;
                $pointsAwarded = 0.0;
            } elseif ($isMissing) {
                $studentDisplay = 'None found';
                $isCorrect = false;
                $pointsAwarded = 0.0;
            } else {
                $studentDisplay = $rawStudentAns;
                if ($caseSensitive) {
                    $isCorrect = ($rawStudentAns === $expected);
                } else {
                    $isCorrect = (mb_strtolower($rawStudentAns) === mb_strtolower($expected));
                }
                $pointsAwarded = $isCorrect ? $itemMax : 0.0;
            }

            if ($isCorrect) {
                $correctCount++;
                $totalAwarded += $pointsAwarded;
                $statusTag = 'CORRECT';
            } else {
                $statusTag = 'INCORRECT';
            }

            $ptsAwardedStr = number_format($pointsAwarded, 2);
            $itemMaxStr = number_format($itemMax, 2);
            $itemLines[] = "* Item #{$itemNum}: [{$statusTag}] — Student Answer: \"{$studentDisplay}\" | Expected: \"{$expected}\" ({$ptsAwardedStr} / {$itemMaxStr} pts)";
        }

        $finalScore = round(min($maxPoints, max(0.0, $totalAwarded)), 2);
        $finalScoreStr = number_format($finalScore, 2);
        $maxPointsStr = number_format($maxPoints, 2);

        $remarks = implode("\n", $itemLines)."\n\n";
        $remarks .= "Summary: Total items correct: {$correctCount} / {$totalItems}. Total score: {$finalScoreStr} / {$maxPointsStr} pts.";

        if (! $isOnTopic) {
            $reason = $topicSummary ?: 'The submitted document does not address the required activity/quiz specifications.';
            $remarks .= " Note: Off-Topic Submission — {$reason}";
        }

        return [
            'score' => $finalScore,
            'remarks' => $remarks,
        ];
    }

    /**
     * Deterministically evaluate and format percentage rate rubrics.
     *
     * @return array{score: float, remarks: string}
     */
    protected function evaluatePercentageRubric(
        array $parsed,
        array $rubricData,
        float $maxPoints,
        bool $isOnTopic,
        string $topicSummary
    ): array {
        $criteria = $rubricData['criteria'] ?? [];
        if (empty($criteria)) {
            return [
                'score' => 0.0,
                'remarks' => 'No percentage criteria configured.',
            ];
        }

        if (! $isOnTopic) {
            $reason = $topicSummary ?: 'The submitted document does not address the required activity specifications or rubrics.';
            $criterionLines = [];
            foreach ($criteria as $idx => $c) {
                $cName = trim((string) ($c['name'] ?? 'Criterion '.($idx + 1)));
                $cPct = (float) ($c['percentage'] ?? 0);
                $cMax = max(0.0, (float) ($c['max_points'] ?? round(($cPct / 100) * $maxPoints, 2)));
                $cMaxStr = number_format($cMax, 2);
                $criterionLines[] = "* [{$cName}] (0.00 / {$cMaxStr} pts): 0.00 points awarded because submission is off-topic.";
            }

            $remarks = implode("\n", $criterionLines)."\n\n";
            $remarks .= 'Summary: Off-Topic Submission (Total Score: 0.00 / '.number_format($maxPoints, 2)." pts). {$reason}";

            return [
                'score' => 0.0,
                'remarks' => $remarks,
            ];
        }

        $rawBreakdown = $parsed['criteria_breakdown'] ?? [];
        if (! is_array($rawBreakdown)) {
            $rawBreakdown = [];
        }

        $criterionLines = [];
        $totalAwarded = 0.0;

        foreach ($criteria as $idx => $c) {
            $cName = trim((string) ($c['name'] ?? 'Criterion '.($idx + 1)));
            $cPct = (float) ($c['percentage'] ?? 0);
            $cMax = max(0.0, (float) ($c['max_points'] ?? round(($cPct / 100) * $maxPoints, 2)));

            // Find matching item from AI extraction
            $extracted = null;
            foreach ($rawBreakdown as $b) {
                if (! is_array($b)) {
                    continue;
                }
                $bName = trim((string) ($b['criterion'] ?? ''));
                if ($bName !== '' && (strcasecmp($bName, $cName) === 0 || str_contains(strtolower($bName), strtolower($cName)) || str_contains(strtolower($cName), strtolower($bName)))) {
                    $extracted = $b;
                    break;
                }
            }
            if (! $extracted && isset($rawBreakdown[$idx]) && is_array($rawBreakdown[$idx])) {
                $extracted = $rawBreakdown[$idx];
            }

            $rawAwarded = isset($extracted['points_awarded']) && is_numeric($extracted['points_awarded'])
                ? (float) $extracted['points_awarded']
                : null;

            if ($rawAwarded !== null) {
                $awarded = min($cMax, max(0.0, $rawAwarded));
            } else {
                $rawScore = isset($parsed['score']) && is_numeric($parsed['score']) ? (float) $parsed['score'] : 0.0;
                $ratio = $maxPoints > 0 ? ($rawScore / $maxPoints) : 0.0;
                $awarded = round(min($cMax, max(0.0, $ratio * $cMax)), 2);
            }

            $totalAwarded += $awarded;
            $justification = trim((string) ($extracted['justification'] ?? $extracted['evidence'] ?? ''));
            if ($justification === '' || str_contains($justification, 'Itemized grading remarks follow')) {
                $justification = $awarded >= $cMax ? 'Criteria fully satisfied.' : 'Partial completion based on submission content.';
            }

            $awardedStr = number_format($awarded, 2);
            $cMaxStr = number_format($cMax, 2);
            $criterionLines[] = "* [{$cName}] ({$awardedStr} / {$cMaxStr} pts): {$justification}";
        }

        $finalScore = round(min($maxPoints, max(0.0, $totalAwarded)), 2);
        $finalScoreStr = number_format($finalScore, 2);
        $maxPointsStr = number_format($maxPoints, 2);

        $remarks = implode("\n", $criterionLines)."\n\n";
        $summaryNote = $topicSummary ?: (! empty($parsed['remarks']) && ! str_contains($parsed['remarks'], 'Itemized grading remarks') ? trim($parsed['remarks']) : 'Evaluated against structured percentage criteria.');
        $remarks .= "Summary: {$summaryNote} (Total Score: {$finalScoreStr} / {$maxPointsStr} pts)";

        return [
            'score' => $finalScore,
            'remarks' => $remarks,
        ];
    }

    /**
     * Common filler and generic academic words excluded from domain anchors.
     */
    protected static array $genericWords = [
        'the', 'and', 'for', 'that', 'this', 'with', 'from', 'have', 'were', 'will',
        'each', 'using', 'used', 'must', 'should', 'could', 'would', 'about', 'after',
        'into', 'been', 'their', 'there', 'what', 'when', 'where', 'which', 'while',
        'your', 'you', 'student', 'activity', 'step', 'task', 'output', 'submit', 'document',
        'format', 'section', 'part', 'page', 'guide', 'exercise', 'practice', 'table', 'figure',
        'title', 'description', 'questions', 'answers', 'evaluation', 'results', 'data',
        'name', 'date', 'score', 'points', 'total', 'summary', 'note', 'notes', 'rubric',
        'criteria', 'laboratory', 'assignment', 'submission', 'screenshot', 'screenshots',
        'class', 'subject', 'course', 'instructor', 'teacher', 'university', 'college',
        'access', 'test', 'department', 'account', 'use', 'can', 'structure', 'following',
        'created', 'create', 'verify', 'required', 'demonstrate', 'assigned', 'appropriate',
        'different', 'specific', 'ensure', 'resources', 'primarily', 'switch', 'repeat',
        'determine', 'allowed', 'expected', 'result', 'indicate', 'simulating', 'simulate',
        'instructions', 'instruction', 'attached', 'evidence', 'points', 'score', 'evaluate',
        'evaluation', 'criteria', 'criterion', 'sample', 'example', 'answers', 'answer',
        'was', 'are', 'had', 'has', 'not', 'being', 'also', 'over', 'under', 'such',
        'only', 'more', 'most', 'some', 'any', 'both', 'all', 'other', 'same', 'too',
        'very', 'may', 'might', 'shall', 'does', 'did', 'done', 'doing', 'than', 'then',
    ];

    /**
     * Extract specific technical and subject-matter domain terms from text.
     *
     * @return array<string, int>
     */
    public function extractTechnicalAnchors(string $text): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}_\/-]/u', ' ', mb_strtolower($text));
        $tokens = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

        $freq = [];
        foreach ($tokens as $t) {
            $len = mb_strlen($t);
            if ($len < 3 || in_array($t, self::$genericWords, true) || is_numeric($t)) {
                continue;
            }
            $freq[$t] = ($freq[$t] ?? 0) + 1;
        }
        arsort($freq);

        return $freq;
    }

    /**
     * Validate whether a student submission addresses the domain of the activity.
     *
     * @return array{is_on_topic: bool, reason: ?string, expected_topic: ?string, student_topic: ?string}
     */
    public function validateTopicRelevance(string $activityText, string $submissionText, ?string $activityTitle = null): array
    {
        $fullActivity = trim(($activityTitle ? $activityTitle."\n\n" : '').$activityText);
        $actAnchors = $this->extractTechnicalAnchors($fullActivity);

        // If activity instructions are brief or have few domain anchors, defer to LLM evaluation
        if (count($actAnchors) < 8) {
            return ['is_on_topic' => true, 'reason' => null, 'expected_topic' => null, 'student_topic' => null];
        }

        // Extract student's domain anchors
        $subAnchors = $this->extractTechnicalAnchors($submissionText);

        // If student submission is very short or brief snippet, defer to LLM
        if (count($subAnchors) < 4) {
            return ['is_on_topic' => true, 'reason' => null, 'expected_topic' => null, 'student_topic' => null];
        }

        // Take top 25 core technical terms from activity
        $topTechnical = array_slice(array_keys($actAnchors), 0, 25);

        $subClean = preg_replace('/[^\p{L}\p{N}_\/-]/u', ' ', mb_strtolower($submissionText));
        $subWords = array_flip(preg_split('/\s+/', $subClean, -1, PREG_SPLIT_NO_EMPTY));

        $matched = [];
        foreach ($topTechnical as $anchor) {
            if (isset($subWords[$anchor])) {
                $matched[] = $anchor;
            }
        }

        $matchCount = count($matched);
        $totalCount = count($topTechnical);

        $studentTopic = implode(', ', array_slice(array_keys($subAnchors), 0, 5));
        $expectedTopic = implode(', ', array_slice($topTechnical, 0, 5));

        // If 0 technical keywords match (or only 1 out of 10+), it is 100% off-topic
        if ($matchCount === 0 || ($totalCount >= 10 && $matchCount <= 1)) {
            return [
                'is_on_topic' => false,
                'expected_topic' => $expectedTopic,
                'student_topic' => $studentTopic,
                'reason' => "The submitted document does not address the required activity ({$expectedTopic}). The submission appears to be an unrelated document focusing on ({$studentTopic}).",
            ];
        }

        return [
            'is_on_topic' => true,
            'expected_topic' => $expectedTopic,
            'student_topic' => $studentTopic,
            'reason' => null,
        ];
    }
}
