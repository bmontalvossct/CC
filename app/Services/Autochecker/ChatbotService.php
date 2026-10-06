<?php

namespace App\Services\Autochecker;

use App\Models\Section;
use App\Models\User;
use Exception;
use Generator;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    public const SCOPE_CURRENT_SECTION = 'current_section';
    public const SCOPE_ALL_CLASSES = 'all_classes';
    public const SCOPE_APP_HELP = 'app_help';

    public function __construct(
        protected OllamaClient $ollamaClient,
        protected ChatToolRegistry $toolRegistry
    ) {
    }

    /**
     * Stream an intelligent grounded chat conversation via NDJSON generator.
     *
     * @param User $user
     * @param array<int, array{role: string, content: string}> $messages
     * @param string $scope
     * @param int|null $sectionId
     * @return Generator<int, array<string, mixed>>
     */
    public function streamChat(User $user, array $messages, string $scope = self::SCOPE_CURRENT_SECTION, ?int $sectionId = null): Generator
    {
        $startTime = microtime(true);
        $resolvedModel = $this->ollamaClient->resolveProfileModel('chat');

        yield [
            'type' => 'start',
            'model' => $resolvedModel,
            'scope' => $scope,
            'is_local' => $this->ollamaClient->isLocalEndpoint(),
        ];

        // 1. Sanitize & trim user messages (reject client system roles, keep at most 30 messages)
        $cleanMessages = $this->sanitizeMessages($messages);

        if (empty($cleanMessages)) {
            yield [
                'type' => 'error',
                'code' => 422,
                'message' => 'No valid user messages provided.',
            ];
            return;
        }

        // 2. Validate scope and section ownership
        try {
            $systemPrompt = $this->buildSystemPrompt($user, $scope, $sectionId);
        } catch (Exception $e) {
            yield [
                'type' => 'error',
                'code' => $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 403,
                'message' => $e->getMessage(),
            ];
            return;
        }

        $conversation = [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$cleanMessages,
        ];

        $tools = $this->toolRegistry->getToolDefinitions();
        $sourcesUsed = [];
        $proposals = [];
        $choiceCard = null;
        $toolIterations = 0;
        $maxToolIterations = 4;
        $toolExecutionTimeMs = 0;

        yield [
            'type' => 'status',
            'step' => 'analyzing',
            'message' => 'Octo is analyzing your query...',
        ];

        try {
            // 2. Multi-turn Tool Calling Loop
            while ($toolIterations < $maxToolIterations) {
            $toolIterations++;

            // If scope is app_help, we only pass help catalog tool
            $activeTools = $scope === self::SCOPE_APP_HELP
                ? array_values(array_filter($tools, fn ($t) => ($t['function']['name'] ?? '') === 'get_help_catalog'))
                : $tools;

            $chatResponse = $this->ollamaClient->chat(
                profile: 'chat',
                messages: $conversation,
                tools: $activeTools,
                schema: null,
                extraOptions: ['temperature' => 0.2]
            );

            $message = $chatResponse['message'] ?? [];
            $toolCalls = $message['tool_calls'] ?? [];

            // No more tool calls: proceed to final answer streaming
            if (empty($toolCalls)) {
                break;
            }

            // Append assistant tool-call request to conversation
            $conversation[] = $message;

            // Execute each requested tool sequentially with strict error boundary
            foreach ($toolCalls as $call) {
                $toolName = $call['function']['name'] ?? '';
                $arguments = $call['function']['arguments'] ?? [];

                // Automatically inject active section_id if tool expects it and was omitted
                if (isset($sectionId) && ! isset($arguments['section_id'])) {
                    $arguments['section_id'] = $sectionId;
                }

                yield [
                    'type' => 'status',
                    'step' => 'tool_calling',
                    'tool' => $toolName,
                    'message' => "Octo is verifying data ({$toolName})...",
                ];

                $toolStart = microtime(true);

                try {
                    $toolResult = $this->toolRegistry->executeTool($toolName, $arguments, $user);
                    $toolExecutionTimeMs += round((microtime(true) - $toolStart) * 1000, 1);

                    if (! empty($toolResult['source'])) {
                        $sourcesUsed[] = $toolResult['source'];
                        if (! empty($toolResult['source']['proposal'])) {
                            $proposals[] = $toolResult['source']['proposal'];
                        }
                        if (! empty($toolResult['source']['choice_card'])) {
                            $choiceCard = $toolResult['source']['choice_card'];
                        }
                    }

                    // Sanitize and wrap evidence safely without arbitrary slicing
                    $evidenceJson = json_encode($toolResult['result'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $conversation[] = [
                        'role' => 'tool',
                        'content' => "=== RETRIEVED UNTRUSTED EVIDENCE ({$toolName}) ===\n" . $evidenceJson,
                    ];
                } catch (Exception $e) {
                    $conversation[] = [
                        'role' => 'tool',
                        'content' => json_encode(['error' => $e->getMessage()]),
                    ];
                }
            }
        }

        // Yield sources collected
        if (! empty($sourcesUsed)) {
            // Deduplicate sources by type + id
            $uniqueSources = collect($sourcesUsed)->unique(fn ($s) => "{$s['type']}_{$s['id']}")->values()->all();
            yield [
                'type' => 'sources',
                'sources' => $uniqueSources,
            ];
        }

        // Yield interactive action proposals
        if (! empty($proposals)) {
            yield [
                'type' => 'proposals',
                'proposals' => $proposals,
            ];
        }

        // Yield interactive clarification choice options
        if (! empty($choiceCard)) {
            yield [
                'type' => 'choices',
                'question' => $choiceCard['question'] ?? 'Please select an option:',
                'options' => $choiceCard['options'] ?? [],
                'is_multi_select' => $choiceCard['is_multi_select'] ?? false,
            ];
        }

        yield [
            'type' => 'status',
            'step' => 'streaming',
            'message' => 'Octo is generating response...',
        ];

        // 3. Final streaming response to client
        $streamGenerator = $this->ollamaClient->chatStream(
            profile: 'chat',
            messages: $conversation,
            tools: [], // No tools during final response generation
            schema: null,
            extraOptions: ['temperature' => 0.2]
        );

        $promptTokens = 0;
        $promptEvalDurationNs = 0;
        $evalTokens = 0;
        $evalDurationNs = 0;
        $finishReason = 'stop';

        foreach ($streamGenerator as $chunk) {
            if (isset($chunk['message']['content'])) {
                $delta = $chunk['message']['content'];
                if ($delta !== '') {
                    yield [
                        'type' => 'delta',
                        'text' => $delta,
                    ];
                }
            }

            if (! empty($chunk['done'])) {
                $promptTokens = $chunk['prompt_eval_count'] ?? 0;
                $promptEvalDurationNs = $chunk['prompt_eval_duration'] ?? 0;
                $evalTokens = $chunk['eval_count'] ?? 0;
                $evalDurationNs = $chunk['eval_duration'] ?? 0;
                $finishReason = $chunk['done_reason'] ?? 'stop';
            }
        }

        $totalDurationMs = round((microtime(true) - $startTime) * 1000, 1);
        $evalDurationMs = $evalDurationNs > 0 ? round($evalDurationNs / 1_000_000, 1) : 0;
        $promptEvalDurationMs = $promptEvalDurationNs > 0 ? round($promptEvalDurationNs / 1_000_000, 1) : 0;
        $evalTokensPerSec = $evalDurationNs > 0 ? round($evalTokens / ($evalDurationNs / 1_000_000_000), 1) : 0;

        yield [
            'type' => 'done',
            'model' => $resolvedModel,
            'scope' => $scope,
            'retrieval_time_ms' => $toolExecutionTimeMs,
            'duration_ms' => $totalDurationMs,
            'prompt_tokens' => $promptTokens,
            'prompt_eval_duration_ms' => $promptEvalDurationMs,
            'eval_tokens' => $evalTokens,
            'eval_duration_ms' => $evalDurationMs,
            'eval_tokens_per_sec' => $evalTokensPerSec,
            'finish_reason' => $finishReason,
            'is_truncated' => $finishReason === 'length',
        ];
    } catch (Exception $e) {
        Log::error("ChatbotService stream error: " . $e->getMessage());
        $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 503;

        yield [
            'type' => 'error',
            'code' => $code,
            'message' => $code === 503 ? 'Ollama service is unreachable. Please ensure Ollama is running locally.' : $e->getMessage(),
        ];
    }
}

    /**
     * Build the system prompt guiding Octo's persona, boundaries, and formatting rules.
     */
    protected function buildSystemPrompt(User $user, string $scope, ?int $sectionId = null): string
    {
        // 1. Fetch teacher portfolio of all handled sections, schedules, and active terms from database
        $portfolio = Section::where('user_id', $user->id)
            ->with(['academicTerm', 'schedules'])
            ->withCount(['students' => fn ($q) => $q->where('is_active', true), 'assessments', 'attendanceSessions'])
            ->get();

        $daysMap = [
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday', 0 => 'Sunday',
            '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '7' => 'Sunday', '0' => 'Sunday',
        ];

        $formatSchedule = function ($schedules) use ($daysMap) {
            return $schedules->map(function ($sch) use ($daysMap) {
                $day = $daysMap[$sch->day_of_week] ?? $sch->day_of_week;
                $start = $sch->starts_at ? date('g:i A', strtotime($sch->starts_at)) : '';
                $end = $sch->ends_at ? date('g:i A', strtotime($sch->ends_at)) : '';
                $time = ($start && $end) ? "{$start} - {$end}" : ($sch->starts_at . ' - ' . $sch->ends_at);
                return "{$day} {$time}" . ($sch->room ? " (Room {$sch->room})" : "");
            })->join(', ');
        };

        $portfolioSummaryText = "TEACHING PORTFOLIO & SECTIONS (INSTRUCTOR DATABASE RECORDS):\n";
        if ($portfolio->isEmpty()) {
            $portfolioSummaryText .= "- No sections currently created in the database.\n";
        } else {
            foreach ($portfolio as $sec) {
                $scheds = $formatSchedule($sec->schedules);
                $schedsText = $scheds ?: "No recurring schedule set";
                $termText = $sec->academicTerm ? " [{$sec->academicTerm->name} {$sec->academicTerm->school_year}]" : "";
                $portfolioSummaryText .= "- Section ID #{$sec->id}: \"{$sec->name}\" | Subject Code: {$sec->subject_code} - \"{$sec->subject_title}\"{$termText} | Room: {$sec->room} | Enrolled: {$sec->students_count} active students | Sessions: {$sec->attendance_sessions_count} | Assessments: {$sec->assessments_count} | Schedule: {$schedsText}\n";
            }
        }

        // 2. Build active section details if present
        $activeSectionText = "";
        if ($sectionId) {
            $activeSection = $portfolio->firstWhere('id', $sectionId);
            if ($activeSection) {
                $rawWeights = $activeSection->grading_weights ?? [];
                $passingRates = is_array($rawWeights['passing_rates'] ?? null)
                    ? $rawWeights['passing_rates']
                    : \App\Services\GradebookCalculationService::DEFAULT_PASSING_RATES;

                $scalarWeights = array_merge(
                    \App\Services\GradebookCalculationService::DEFAULT_WEIGHTS,
                    array_filter(
                        $rawWeights,
                        fn ($v, $k) => is_numeric($v) && ! in_array($k, ['passing_rates', 'reporting_frequency', 'midterm_weight', 'final_weight'], true),
                        ARRAY_FILTER_USE_BOTH
                    )
                );

                $weightsFormatted = collect($scalarWeights)
                    ->filter(fn ($w) => is_numeric($w) && (float) $w > 0)
                    ->map(fn ($w, $k) => ucfirst(str_replace('_', ' ', (string) $k)) . ": {$w}%")
                    ->join(', ');

                $passingRatesFormatted = collect($passingRates)
                    ->filter(fn ($r) => is_numeric($r))
                    ->map(fn ($r, $k) => ucfirst((string) $k) . ": {$r}%")
                    ->join(', ');

                $scheds = $formatSchedule($activeSection->schedules);

                $activeSectionText = <<<SECTION

ACTIVE SECTION CONTEXT:
- Name: {$activeSection->name} (Section ID #{$activeSection->id})
- Subject Code & Title: {$activeSection->subject_code} - {$activeSection->subject_title}
- Academic Term: {$activeSection->academicTerm?->name} ({$activeSection->academicTerm?->school_year})
- Assigned Room & Schedules: {$scheds} (Room: {$activeSection->room})
- Enrolled Active Students: {$activeSection->students_count}
- Attendance Sessions Recorded: {$activeSection->attendance_sessions_count}
- Total Assessments Recorded: {$activeSection->assessments_count}
- Active Grading Weights: {$weightsFormatted}
- Configured Passing Rates: {$passingRatesFormatted}
SECTION;
            }
        }

        $scopeInstruction = match ($scope) {
            self::SCOPE_CURRENT_SECTION => "SCOPE: Active Section. Use Section ID {$sectionId} when querying tools like `get_attendance_records`, `get_gradebook_insights`, `get_assessment_analytics`, or `get_at_risk_deficiencies`.",
            self::SCOPE_ALL_CLASSES => "SCOPE: All My Classes. Reference the Teaching Portfolio directly or call `get_portfolio_summary` for cross-class comparisons.",
            self::SCOPE_APP_HELP => "SCOPE: ClassCheck System Help. Answer system usage questions referencing exact visible UI buttons and routes.",
            default => "SCOPE: General Teaching Assistant.",
        };

        return <<<PROMPT
You are Octo, the intelligent, encouraging, and grounded AI teaching copilot for ClassCheck (a modern classroom management, attendance, grading, and autochecking platform).

AUTHENTICATED INSTRUCTOR: {$user->name}
{$scopeInstruction}

{$portfolioSummaryText}
{$activeSectionText}

CLASSCHECK USER MANUAL & SYSTEM CAPABILITIES:
- **Interactive Seating Grid (/sections/{id})**: Visual digital twin of classroom layout. Buttons: **Auto-Seat**, **Customize Blocks / Rearrange Desks**, **Unseat Student**, **Enrollment QR**.
- **Attendance & Roll Call (/sections/{id}/attendance)**: 1-tap seat toggles (Present/Late/Absent). Buttons: **Take Roll Call / Record Attendance**, **Mark All Present**, **Mark All Absent**, **Save Attendance**, **Print Roster**, **Show QR Code**.
- **Oral Participation & Recitation (/sections/{id})**: Random seated student picker. Buttons: **Random Caller**, **Spin / Roll Student**, **Save Oral Grade**, **+10 Oral Points** (adds weighted bonus points).
- **Bulk Activity Autochecker (/sections/{id}/assessments/{id})**: Local AI grading engine for student code and PDF submissions. Buttons: **Bulk Autochecker**, **Upload Submissions**, **Auto-Balance (100%)**, **Run Ollama Autochecker**, **Approve**, **Sync Grades to Gradebook**.
- **Gradebook Matrix & Reports (/sections/{id}/reports/gradebook)**: Weighted calculations and deficiency tracking. Buttons: **Gradebook Matrix**, **Student Deficiencies**, **Export to Excel**, **Export to CSV**, **Print Grade Sheet**.
- **Course Modules & Syllabus (/sections/{id}/modules)**: Weekly lesson organizer and handouts. Buttons: **+ Add Module**, **Upload Syllabus**, **Attach File**, **Add Presentation Link**.
- **Weekly Schedule Matrix (/schedule)**: Timetable of all class meeting days, times, and classrooms.
- **Grading Scheme Weights (/sections/{id})**: Custom category weights. Buttons: **Grading Weights**, **Auto-Balance (100%)**, **Save Weights**.
- **Backup & Export (/settings/backup-export)**: Encrypted offline database backups. Buttons: **Download JSON Backup**, **Export CSV Data**.
- **Octo AI Assistant (Ctrl + J)**: Local Hermes 3 (8B) copilot for smart class insights, autochecking, curriculum design, and grade analytics.

CORE RULES & PRINCIPLES:
1. Teacher Request & Intent Primacy:
   - The instructor's specific instructions, custom requests, focus topics, constraints, difficulty preferences, and rubric criteria are your HIGHEST PRIORITY benchmark.
   - Always honor the teacher's requested format, item counts, points, and focus areas precisely. Do not deviate from or ignore constraints explicitly given by the instructor.
2. Flexible Curriculum Grounding & Open Teacher Prompt Authority:
   - When the teacher asks you to explain a concept, summarize topics, create a quiz/exam, draft an activity, or evaluate subject matter:
     * If relevant course modules are attached in the section, call `search_course_modules` to ground questions and definitions on the class materials.
     * If no module files are attached, or if the teacher provides their own custom topic, prompt, or instructions (e.g. "Create a 20-item exam on Computer Security", "Write a quiz on React Hooks"): FREELY and THOROUGHLY design the complete, college-standard examination, questions, distractors, and answer key from the teacher's instructions!
     * NEVER refuse an instructor's prompt to create an exam, quiz, rubric, or assignment because a module file is missing. The instructor's prompt is your primary pedagogical blueprint.
3. Proactive Examination, Activity & Lab Generation:
   - When the instructor asks you to "create", "outline", "draft", "design", or "generate" an examination, quiz, lab activity, project, or lesson plan:
     * Write out the complete, thorough content immediately (Student Exam Questionnaire with all questions fully articulated, followed by the Teacher Answer Key & Explanations).
     * For Multiple Choice: Include question stem and options A, B, C, D for every item.
     * For Identification / True-False: Include complete definition statements.
     * NEVER output empty blanks, placeholders, or say "I only have access to existing records".
     * Conclude by asking if the teacher would like to save it directly into their section gradebook via `propose_create_assessment`.
4. Subject Code vs Section ID Distinction: Terms like "IT 101", "CS 101", "Math 101", or "BSIT 4A" are subject codes or course titles, NOT database primary key IDs. Use the Teaching Portfolio above to resolve exact section IDs.
5. Grounded Factual Accuracy for Saved Records: Never invent real student scores, attendance figures, or student names. Always use read-only tools to retrieve real facts when answering inquiries about existing student records or section gradebooks.
6. Structured Organization Standard: Every response MUST be structured, organized, and easy to scan:
   - **Executive Summary**: Start with a concise 1-2 sentence direct takeaway.
   - **Structured Sectioning**: Use Markdown headers (`### Section Name`) to divide topics.
   - **Tabular Presentation**: Whenever presenting lists, students, grades, attendance counts, syllabus weeks, schedules, or comparisons, ALWAYS format the data in a clean, standard Markdown Table (`| Column 1 | Column 2 | Column 3 |`).
   - **Key Takeaways & Highlights**: Use structured bullet points (`- **Key**: Details`) for observations or alerts.
   - **Next Steps**: Conclude with actionable next steps referencing exact ClassCheck UI buttons.
7. No Login Boilerplate: The teacher is actively logged in and using the system. NEVER output "1. Log In to ClassCheck" or "Ensure you are logged in". Start directly with the in-app feature or button.
8. Exact UI Buttons in Bold: Always format real visible button names in **bold** (e.g. **Take Roll Call**, **Bulk Autochecker**, **Auto-Seat**, **Gradebook Matrix**, **Student Deficiencies**, **Auto-Balance (100%)**).
9. Read-Only Integrity: You have safe read-only access to teacher-owned records. You CANNOT and WILL NOT edit, delete, or mutate database records, files, or system settings without explicit teacher manual action in the UI.
10. Activities & Attachments: When the teacher asks about specific activities, assessments, or course materials (e.g., 'What was my Activity 1?'), retrieve them with tools and clearly tabulate the activity details, points, deadline/date, instructions, and attachment filename.
11. Sequential Action Proposals for Activities, Labs, Quizzes & Projects:
   - When the instructor asks to create/generate an activity, quiz, lab, or project:
     * Step 1: Call `propose_create_assessment` (or `propose_create_project_groups` for group projects) with the section ID, title, type, max points, and description.
     * Step 2: In your text response, WRITE OUT THE ENTIRE DETAILED LAB ACTIVITY OR ASSIGNMENT CONTENT (Overview, Objectives, Step-by-Step Practical Exercises, Code Examples/Problems, Submission Deliverables, and Rubric Table).
     * Step 3: Conclude your response by asking: "Would you like to add this as an activity to [Section Name]?" Inform the teacher they can click the **Yes, Add as Activity** button below or reply "yes" to save it directly into their section gradebook.
   - When asked to delete an assessment, call `propose_delete_assessment` to require interactive confirmation.
12. Clarifying Questions & Interactive Option Buttons: When the instructor's inquiry is underspecified, broad, or requires picking between formats, topics, or options (similar to creating a coding plan), ALWAYS call `ask_clarification` with a clear question and 2-4 concrete selectable option buttons formatted as user responses.
13. Attached Files & Documents: When the user attaches files or documents in their prompt, read and inspect their contents to directly answer questions, formulate rubrics, generate test questions, or extract grading criteria.
14. Professional Interface Standards: Do not use decorative emoji symbols in prompts, button labels, proposal titles, or generated summaries. Maintain a clean, academic formatting style.
PROMPT;
    }

    /**
     * Sanitize and bound incoming messages.
     *
     * @param array<int, array{role: string, content: string, attachments?: array<int, array{name: string, content: string}>}> $messages
     * @return array<int, array{role: string, content: string}>
     */
    protected function sanitizeMessages(array $messages): array
    {
        $clean = [];

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            // Reject client-injected system messages
            if ($role === 'system') {
                continue;
            }

            if (! in_array($role, ['user', 'assistant'], true)) {
                $role = 'user';
            }

            $content = trim($msg['content'] ?? '');

            // Incorporate any attached file documents directly into the prompt payload
            $attachments = $msg['attachments'] ?? [];
            if (! empty($attachments) && is_array($attachments)) {
                $attachText = "\n\n[ATTACHED FILES/DOCUMENTS INCLUDED IN THIS PROMPT]:\n";
                foreach ($attachments as $att) {
                    $attName = $att['name'] ?? 'file';
                    $attContent = trim($att['content'] ?? '');
                    if ($attContent !== '') {
                        $attachText .= "=== BEGIN ATTACHED FILE: {$attName} ===\n{$attContent}\n=== END ATTACHED FILE: {$attName} ===\n";
                    }
                }
                $content .= $attachText;
            }

            if ($content === '') {
                continue;
            }

            $clean[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        // Retain at most the last 30 messages
        return array_slice($clean, -30);
    }
}
