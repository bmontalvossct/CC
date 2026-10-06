<?php

namespace App\Services\Autochecker;

use App\Models\CourseModule;
use App\Models\Section;
use Exception;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ExamGeneratorService
{
    public function __construct(
        protected OllamaClient $ollamaClient,
        protected FileContentExtractorService $extractor,
        protected ?ModuleRagService $ragService = null,
        protected ?GeminiClient $geminiClient = null,
    ) {
        $this->ragService ??= app(ModuleRagService::class);
        $this->geminiClient ??= app(GeminiClient::class);
    }

    /**
     * Resolve the preferred model name for Hermes 3.
     */
    public function resolveHermesModel(): string
    {
        $configured = config('autochecker.profiles.chat.primary_model', 'hermes3:8b');
        $installed = collect($this->ollamaClient->getModels())->pluck('name')->all();

        // 1. Look for installed hermes3 variations
        foreach ($installed as $m) {
            if (stripos($m, 'hermes3') !== false || stripos($m, 'hermes') !== false) {
                return $m;
            }
        }

        // 2. Check if configured model is installed
        if (in_array($configured, $installed, true)) {
            return $configured;
        }

        // 3. Fallback to profile resolution or default
        return $this->ollamaClient->resolveProfileModel('chat');
    }

    /**
     * Check if Hermes or an Ollama model is available, plus Gemini Cloud availability.
     *
     * @return array<string, mixed>
     */
    public function getHermesStatus(): array
    {
        $ping = $this->ollamaClient->ping();
        $models = $ping['online'] ? $this->ollamaClient->getModels() : [];
        $model = $this->resolveHermesModel();
        $isHermes = stripos($model, 'hermes') !== false;

        $geminiAvailable = $this->geminiClient?->isAvailable() ?? false;
        $geminiModel = $this->geminiClient?->getModel() ?? 'gemini-2.5-flash';

        return [
            'online' => $geminiAvailable || $ping['online'],
            'latency_ms' => $ping['latency_ms'] ?? null,
            'model' => $geminiAvailable ? $geminiModel : $model,
            'is_hermes' => $isHermes,
            'installed_models' => $models,
            'gemini_available' => $geminiAvailable,
            'gemini_model' => $geminiModel,
            'active_engine' => $geminiAvailable ? 'gemini' : 'ollama',
        ];
    }

    /**
     * Extract syllabus, topic summaries, and file text from selected modules using RAG grounding.
     *
     * @param  Collection<int, CourseModule>  $modules
     * @param  array<string, mixed>  $config
     * @return array<int, array{id: int, module_number: string, title: string, description: ?string, excerpt: string, has_file: bool, file_name: ?string}>
     */
    public function extractCurriculumContext(Collection $modules, array $config = []): array
    {
        if ($modules->isEmpty()) {
            return [];
        }

        $teacherQuery = trim(implode(' ', array_filter([
            $config['title'] ?? '',
            $config['instructions'] ?? '',
            implode(' ', array_column($config['test_types'] ?? [], 'label')),
            implode(' ', array_column($config['test_types'] ?? [], 'type')),
        ])));

        return $this->ragService->retrieveForCurriculum($modules, $teacherQuery, 14000);
    }

    /**
     * Generate structured examination stream from Gemini Flash or Hermes 3.
     *
     * @param  Collection<int, CourseModule>  $modules
     * @param  array<string, mixed>  $config
     * @return Generator<int, array<string, mixed>>
     */
    public function streamGenerateExam(Section $section, Collection $modules, array $config): Generator
    {
        $startTime = microtime(true);
        $provider = $config['provider'] ?? 'auto';

        $useGemini = ($provider === 'gemini')
            || ($provider !== 'ollama' && ($this->geminiClient?->isAvailable() ?? false));

        $resolvedModel = $useGemini
            ? $this->geminiClient->getModel()
            : $this->resolveHermesModel();

        if ($modules->isNotEmpty()) {
            yield [
                'type' => 'status',
                'step' => 'curriculum',
                'message' => "Synthesizing curriculum from {$modules->count()} selected module(s)...",
            ];
            $curriculum = $this->extractCurriculumContext($modules, $config);
        } else {
            yield [
                'type' => 'status',
                'step' => 'curriculum',
                'message' => 'Configuring prompt requirements and topic scope...',
            ];
            $curriculum = [];
        }

        yield [
            'type' => 'status',
            'step' => 'prompting',
            'message' => "Configuring exam structure for {$resolvedModel}...",
        ];

        $prompts = $this->buildPrompts($section, $curriculum, $config);

        $engineLabel = $useGemini ? 'Gemini 2.5 Flash' : 'Hermes 3';
        yield [
            'type' => 'status',
            'step' => 'generating',
            'message' => "{$engineLabel} is drafting the examination paper and answer key...",
            'model' => $resolvedModel,
            'engine' => $useGemini ? 'gemini' : 'ollama',
        ];

        $completeText = '';
        $tokensCount = 0;

        if ($useGemini) {
            try {
                $streamGenerator = $this->geminiClient->streamGenerateContent(
                    systemPrompt: $prompts['system'],
                    userPrompt: $prompts['user'],
                    extraOptions: [
                        'temperature' => 0.25,
                        'max_tokens' => 8192,
                    ]
                );

                foreach ($streamGenerator as $chunk) {
                    if (isset($chunk['text']) && $chunk['text'] !== '') {
                        $delta = $chunk['text'];
                        $completeText .= $delta;
                        $tokensCount++;
                        yield [
                            'type' => 'delta',
                            'text' => $delta,
                        ];
                    }
                }
            } catch (Exception $e) {
                Log::warning('Gemini streaming failed, falling back to Ollama: '.$e->getMessage());
                // If Gemini fails or times out, fallback to local Ollama if online
                $useGemini = false;
            }
        }

        if (! $useGemini && empty($completeText)) {
            $messages = [
                ['role' => 'system', 'content' => $prompts['system']],
                ['role' => 'user', 'content' => $prompts['user']],
            ];

            $streamGenerator = $this->ollamaClient->chatStream(
                profile: 'chat',
                messages: $messages,
                tools: [],
                schema: null,
                extraOptions: [
                    'temperature' => 0.25,
                    'top_p' => 0.9,
                    'num_ctx' => 16384,
                    'num_predict' => 8192,
                ]
            );

            foreach ($streamGenerator as $chunk) {
                if (isset($chunk['message']['content'])) {
                    $delta = $chunk['message']['content'];
                    if ($delta !== '') {
                        $completeText .= $delta;
                        $tokensCount++;
                        yield [
                            'type' => 'delta',
                            'text' => $delta,
                        ];
                    }
                }
            }
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 1);
        $sections = $this->splitExamSections($completeText);
        $structuredRubric = $this->extractStructuredRubricItems($sections['answer_key'], $config['test_types'] ?? []);

        yield [
            'type' => 'done',
            'model' => $resolvedModel,
            'duration_ms' => $durationMs,
            'tokens_count' => $tokensCount,
            'total_items' => $config['total_items'] ?? 0,
            'total_points' => $config['total_points'] ?? 0,
            'student_paper' => $sections['student_paper'],
            'answer_key' => $sections['answer_key'],
            'full_exam' => $completeText,
            'structured_rubric' => $structuredRubric,
            'engine' => $useGemini ? 'gemini' : 'ollama',
        ];
    }

    /**
     * Build pedagogical system and user prompts for Hermes 3.
     */
    protected function buildPrompts(Section $section, array $curriculum, array $config): array
    {
        $examTitle = $config['title'] ?? 'Examination';
        $termPeriod = ucfirst($config['term_period'] ?? 'Midterm');
        $difficulty = $config['difficulty'] ?? 'balanced';
        $customInstructions = trim($config['instructions'] ?? '');
        $testTypes = $config['test_types'] ?? [];

        $totalItems = $config['total_items'] ?? 0;
        $totalPoints = $config['total_points'] ?? 0;

        $partsSpec = [];
        $partIndex = 1;
        $romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];

        foreach ($testTypes as $tt) {
            $num = $romanNumerals[$partIndex - 1] ?? (string) $partIndex;
            $name = $tt['label'] ?? ucfirst($tt['type']);
            $count = (int) ($tt['items_count'] ?? 1);
            $pts = (float) ($tt['points_per_item'] ?? 1);
            $subtotal = $count * $pts;
            $type = $tt['type'] ?? '';

            $rule = match ($type) {
                'identification' => "Write exactly {$count} complete definition sentences for students to identify the term. NEVER output empty blanks without definitions.",
                'multiple_choice' => "Write exactly {$count} questions with a complete question stem and 4 choices (A, B, C, D) on separate lines.",
                'true_false' => "Write exactly {$count} complete, articulate statements for students to evaluate as True or False.",
                'enumeration' => "Write prompts asking students to enumerate key concepts with {$count} total item slots.",
                'explanation' => "Write {$count} in-depth conceptual or situational essay questions.",
                'code_review' => "Write {$count} code questions featuring realistic, functional code snippets with actual security or logic flaws. Never use trivial dummy code like '# Process sensitive data'.",
                default => "Generate {$count} items ({$pts} pts each).",
            };

            $partsSpec[] = "- **Part {$num}: {$name}** ({$count} items, {$pts} point(s) each = {$subtotal} pts)\n  * Direct Instruction: {$rule}";
            $partIndex++;
        }
        $partsText = implode("\n", $partsSpec);

        $moduleSummaries = [];
        foreach ($curriculum as $c) {
            $entry = "### {$c['module_number']}: {$c['title']}\n";
            if (! empty($c['description'])) {
                $entry .= "Topics/Objectives: {$c['description']}\n";
            }
            if (! empty($c['excerpt'])) {
                $entry .= "Curricular Content:\n{$c['excerpt']}\n";
            }
            $moduleSummaries[] = $entry;
        }
        $modulesText = ! empty($moduleSummaries)
            ? implode("\n---\n", $moduleSummaries)
            : "No course module slides attached. Derive authentic, rigorous collegiate examination questions matching the course title ({$section->subject_code} - {$section->subject_title}), the exam title ({$examTitle}), standard collegiate syllabus topics, and the Teacher's Special Focus / Instructions.";

        $systemPrompt = <<<'PROMPT'
You are an expert collegiate professor, curriculum evaluator, and examination creator.
Your objective is to generate an authentic, rigorous, college-standard examination paper and a corresponding teacher answer key based on the course topics and instructor directives.

### TEACHER DIRECTIVE & GROUNDING AUTHORITY
1. TEACHER INSTRUCTIONS ARE SUPREME: The teacher's custom instructions, special focus requests, test configurations, and stated requirements are your absolute highest priority. If the teacher asks to focus on certain chapters, topics, difficulty levels, or formatting, you MUST obey those instructions completely.
2. CURRICULUM GROUNDING: When attached course modules are provided, every question must align with that curriculum. If no module files are attached, design comprehensive collegiate questions matching the subject title and the teacher's stated instructions.
3. ZERO OUT-OF-CURRICULUM DRIFT: Do not introduce unrelated external concepts unless requested or implied by the course syllabus.

### STRICT RULES FOR QUESTION STIPULATION (ZERO EMPTY QUESTIONS)
1. CRITICAL: NEVER output an empty blank, a bare number, or a question without its text!
   - BAD (FORBIDDEN): "1. __________ 1." or "1. [ ] 1." or "1. A __________ 1."
   - GOOD: "1. ____________________ The systematic process of identifying, analyzing, and evaluating risks to information assets."
2. In IDENTIFICATION: Every single item MUST contain a complete, thorough definition or description sentence.
3. In MULTIPLE CHOICE: Every item MUST have a complete question sentence followed by 4 distinct lettered options (A, B, C, D) on separate lines.
4. In TRUE OR FALSE: Every item MUST have a complete, articulate declarative statement for students to evaluate.
5. In CODE REVIEW: Code snippets must be realistic, functional code with genuine security or logic issues (e.g. SQL injection, command injection, hardcoded secrets, buffer boundary flaw). NEVER use dummy comments like "# Process sensitive data".
6. In ENUMERATION: Give a clear instruction specifying what to enumerate with clean blank lines for student answers.

### MANDATORY OUTPUT STRUCTURE
You MUST generate TWO clearly marked sections separated by exact delimiter headings:

1. `=== STUDENT EXAM QUESTIONNAIRE ===`
   - Formal test paper ready to be printed and handed to students.
   - Header with: Course Subject Code & Title, Exam Title, Student Name, Section, Date, Score.
   - General Directions for students.
   - Each part clearly numbered with directions, items, and point values.

Follow these EXACT structural templates for each test type:

#### Identification Template:
Part I: Identification (N items - 1 pt each = N pts)
Directions: Identify the term, concept, or standard described in each item. Write your answer on the space provided before each number.
1. ____________________ The systematic process of identifying, analyzing, and evaluating security risks to an organization's digital assets.
2. ____________________ A globally recognized cybersecurity framework developed by NIST to assist organizations in managing risk.

#### Enumeration Template:
Part II: Enumeration (N items - 2 pts each = N pts)
Directions: Enumerate the items requested in each question.
1-3. Enumerate the three core pillars of the CIA Triad:
     1. __________________________________________________
     2. __________________________________________________
     3. __________________________________________________
4-5. Give two commonly used risk assessment methodologies:
     4. __________________________________________________
     5. __________________________________________________

#### Explanation / Essay Template:
Part III: Explanation / Essay (N items - 5 pts each = N pts)
Directions: Answer each question thoroughly and comprehensively.
1. Distinguish between quantitative risk assessment and qualitative risk assessment. Under what conditions would an organization prefer qualitative analysis over quantitative analysis? (5 pts)
   Answer:
   __________________________________________________________________________________________
   __________________________________________________________________________________________

#### Code Review & Analysis Template:
Part IV: Code Review & Analysis (N items - 5 pts each = N pts)
Directions: Analyze each code snippet below, answer the accompanying questions, and propose a secure refactored solution.
1. Examine the following Python authentication snippet:
```python
import sqlite3

def authenticate(username, password):
    conn = sqlite3.connect("company.db")
    cursor = conn.cursor()
    # Execute query
    query = f"SELECT * FROM users WHERE username = '{username}' AND password = '{password}'"
    cursor.execute(query)
    return cursor.fetchone()
```
a) Identify the specific security vulnerability present in this implementation. (2 pts)
b) Explain how an attacker could exploit this vulnerability to bypass authentication. (1 pt)
c) Provide the corrected, secure code using parameterized queries. (2 pts)

#### Multiple Choice Template:
Part V: Multiple Choice (N items - 1 pt each = N pts)
Directions: Choose the letter of the correct answer. Write the letter of your choice in the brackets provided.
1. [   ] Which metric represents the expected monetary loss an organization incurs each time a specific threat exploits a vulnerability?
   A. Annualized Loss Expectancy (ALE)
   B. Single Loss Expectancy (SLE)
   C. Annualized Rate of Occurrence (ARO)
   D. Exposure Factor (EF)
2. [   ] In symmetric key cryptography, which statement is TRUE regarding key management?
   A. A single shared secret key is used for both encryption and decryption.
   B. Each party creates their own public and private key pair.
   C. The sender uses the recipient's public key to encrypt the payload.
   D. Digital certificates are strictly mandatory for secret key generation.

#### True or False Template:
Part VI: True or False (N items - 1 pt each = N pts)
Directions: Write TRUE if the statement is correct; otherwise, write FALSE.
1. [   ] A vulnerability cannot result in a security breach without an active threat agent capable of exploiting it.
2. [   ] Residual risk refers to the total inherent risk that exists in an environment before any security controls are deployed.
3. [   ] In qualitative risk assessment, risk exposure is expressed in monetary currency values rather than relative severity ratings.

2. `=== TEACHER ANSWER KEY & RUBRIC ===`
   - Complete, authoritative grading key for the instructor.
   - For Identification: Exact terms and accepted variations.
   - For Enumeration: Complete list of all accepted items.
   - For Explanation: Core concepts required for full credit, partial credit breakdown, and grading criteria.
   - For Code Review: Exact bug identification, exploitation explanation, and secure refactored code.
   - For Multiple Choice: Correct letter, name of the concept, and brief 1-sentence rationale.
   - For True or False: Correct answer (`TRUE` or `FALSE`) plus 1-sentence justification if False.
PROMPT;

        $userPrompt = <<<USER_PROMPT
Please draft a formal {$termPeriod} Examination based on the course modules below.

## Exam Details:
- **Course**: {$section->subject_code} - {$section->subject_title} ({$section->name})
- **Exam Title**: {$examTitle}
- **Term Period**: {$termPeriod}
- **Target Total Items**: {$totalItems} items
- **Target Total Score**: {$totalPoints} points
- **Academic Tone / Difficulty**: {$difficulty}

## Required Test Structure:
{$partsText}

USER_INSTRUCTIONS_PLACEHOLDER

## Selected Course Modules & Syllabus Material:
{$modulesText}

## IMPORTANT GENERATION INSTRUCTIONS:
- Every single question MUST be fully written out. Do NOT write empty blanks or placeholders.
- In Part I (Identification): Write the full definition or clue for each of the {$totalItems} items.
- In Part V (Multiple Choice): Write the question and all 4 choices (A, B, C, D) for each item.
- In Part VI (True or False): Write the complete statement for each item.
- In Part IV (Code Review): Provide realistic code with real vulnerabilities, never empty dummy functions.
- Follow immediately with the complete `=== TEACHER ANSWER KEY & RUBRIC ===`.

Now, generate the complete examination following the required format:
1. `=== STUDENT EXAM QUESTIONNAIRE ===`
2. `=== TEACHER ANSWER KEY & RUBRIC ===`
USER_PROMPT;

        $customInstructionText = ! empty($customInstructions)
            ? "## Teacher's Special Focus / Instructions:\n{$customInstructions}\n"
            : '';

        $userPrompt = str_replace('USER_INSTRUCTIONS_PLACEHOLDER', $customInstructionText, $userPrompt);

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
        ];
    }

    /**
     * Split full generation text into student questionnaire and teacher answer key.
     *
     * @return array{student_paper: string, answer_key: string, full_text: string}
     */
    public function splitExamSections(string $fullText): array
    {
        $studentDelimiter = '=== STUDENT EXAM QUESTIONNAIRE ===';
        $teacherDelimiter = '=== TEACHER ANSWER KEY & RUBRIC ===';

        $studentPos = strpos($fullText, $studentDelimiter);
        $teacherPos = strpos($fullText, $teacherDelimiter);

        if ($studentPos !== false && $teacherPos !== false && $teacherPos > $studentPos) {
            $studentStart = $studentPos + strlen($studentDelimiter);
            $studentPaper = trim(substr($fullText, $studentStart, $teacherPos - $studentStart));
            $answerKey = trim(substr($fullText, $teacherPos + strlen($teacherDelimiter)));

            return [
                'student_paper' => $studentPaper,
                'answer_key' => $answerKey,
                'full_text' => $fullText,
            ];
        }

        // Fallback: If headings varied slightly (case or symbols)
        if (preg_match('/(===|\#\#\#|\#\#)\s*STUDENT.*?QUESTIONNAIRE.*?(===|\#\#\#|\#\#)?/i', $fullText, $m1, PREG_OFFSET_CAPTURE) &&
            preg_match('/(===|\#\#\#|\#\#)\s*TEACHER.*?ANSWER\s*KEY.*?(===|\#\#\#|\#\#)?/i', $fullText, $m2, PREG_OFFSET_CAPTURE)) {
            $sOffset = $m1[0][1] + strlen($m1[0][0]);
            $tOffset = $m2[0][1];
            $tEnd = $m2[0][1] + strlen($m2[0][0]);

            $studentPaper = trim(substr($fullText, $sOffset, $tOffset - $sOffset));
            $answerKey = trim(substr($fullText, $tEnd));

            return [
                'student_paper' => $studentPaper,
                'answer_key' => $answerKey,
                'full_text' => $fullText,
            ];
        }

        return [
            'student_paper' => trim($fullText),
            'answer_key' => '',
            'full_text' => $fullText,
        ];
    }

    /**
     * Parse answer key into structured rubric items for autochecking and grading.
     *
     * @return array<string, mixed>
     */
    public function extractStructuredRubricItems(string $answerKeyText, array $testTypes): array
    {
        if (empty(trim($answerKeyText))) {
            return [
                'rubric_type' => 'answer_key',
                'items' => [],
                'raw_text' => '',
            ];
        }

        $items = [];
        $lines = explode("\n", $answerKeyText);
        $itemNumber = 1;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            // Match lines like: "1. Answer", "1) Answer", "**1.** Answer", or "Item 1: Answer"
            if (preg_match('/^(?:\*{0,2}(?:Item\s+)?(\d+)[\.\)\:]\*{0,2})\s*(.+)$/i', $trimmed, $match)) {
                $parsedNum = (int) $match[1];
                $answerText = trim($match[2]);

                $items[] = [
                    'id' => 'item_'.$parsedNum,
                    'item_number' => $parsedNum,
                    'expected_answer' => $answerText,
                    'points' => 1.0,
                    'case_sensitive' => false,
                ];
                $itemNumber = max($itemNumber, $parsedNum + 1);
            }
        }

        return [
            'rubric_type' => 'answer_key',
            'items' => $items,
            'raw_text' => $answerKeyText,
        ];
    }
}
