<?php

namespace App\Services\Autochecker;

use App\Models\AttendanceRecord;
use App\Models\CourseModule;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\GradebookCalculationService;
use Exception;
use Illuminate\Support\Facades\Log;

class ChatToolRegistry
{
    public function __construct(
        protected GradebookCalculationService $gradebookService
    ) {
    }

    /**
     * Get Ollama tool schema definitions formatted for Ollama /api/chat.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_portfolio_summary',
                    'description' => 'Retrieve summary of all handled classes, enrolled student counts, and active academic terms for the authenticated teacher.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_section_summary',
                    'description' => 'Retrieve overview of a specific section (subject code, title, room, schedule, student count, total sessions, assessments).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the section to query.',
                            ],
                        ],
                        'required' => ['section_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_gradebook_insights',
                    'description' => 'Retrieve authoritative gradebook metrics for a section: class average, at-risk students, top performers, passing/failing counts, category weights, and missing tasks.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the section to analyze.',
                            ],
                        ],
                        'required' => ['section_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_attendance_records',
                    'description' => 'Retrieve attendance session history, student absence counts, late records, and attendance percentages for a section.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the section.',
                            ],
                        ],
                        'required' => ['section_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_student_detail',
                    'description' => 'Retrieve detailed information for a specific student: name, student number, seat label, absences, recitation scores, and individual task grades.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the section the student belongs to.',
                            ],
                            'query' => [
                                'type' => 'string',
                                'description' => 'Student name or student number to search for.',
                            ],
                        ],
                        'required' => ['section_id', 'query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_course_materials',
                    'description' => 'Retrieve ALREADY SAVED syllabus or uploaded module files for an existing class in ClassCheck. NEVER call this tool when the user is asking you to CREATE, DRAFT, GENERATE, or OUTLINE a new syllabus, lesson plan, or course material (use your own pedagogical knowledge to write the syllabus directly).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section_id' => [
                                'type' => 'integer',
                                'description' => 'The numeric database ID of the section.',
                            ],
                        ],
                        'required' => ['section_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_help_catalog',
                    'description' => 'Retrieve official ClassCheck system documentation, exact visible button names, navigation steps, and feature tutorials.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Topic, feature, or button keyword (e.g., "autochecker", "seating", "attendance", "weights").',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_assessment_analytics',
                    'description' => 'Retrieve detailed statistical analytics for assessments in a section: score distributions, class averages, highest/lowest scores, submission counts, and completion rates.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the section.',
                            ],
                            'assessment_id' => [
                                'type' => 'integer',
                                'description' => 'Optional ID of a specific assessment to analyze. Omit to retrieve analytics for all assessments.',
                            ],
                        ],
                        'required' => ['section_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_at_risk_deficiencies',
                    'description' => 'Retrieve list of all struggling and at-risk students in a section: identifies students with critical absences (>2), failing grades (<75%), and missing tasks with actionable recommendations.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the section.',
                            ],
                        ],
                        'required' => ['section_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'ask_clarification',
                    'description' => 'Present an interactive multiple-choice question card with selectable buttons to the teacher to clarify requirements or pick between options before generating plans or materials.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => [
                                'type' => 'string',
                                'description' => 'The clarifying question or decision prompt to ask.',
                            ],
                            'options' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Array of 2 to 5 concrete choice labels formatted as direct user responses (e.g. ["15-Item Multiple Choice", "10 MC + 2 Coding Problems", "Custom Rubric"]).',
                            ],
                            'is_multi_select' => [
                                'type' => 'boolean',
                                'description' => 'Whether the user can select multiple options (default: false).',
                            ],
                        ],
                        'required' => ['question', 'options'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'propose_create_assessment',
                    'description' => 'Propose adding a generated activity, laboratory, quiz, or exam directly into the section gradebook. Attaches an interactive confirmation card with Yes/No/Edit buttons in the chat.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the section to add the assessment to.',
                            ],
                            'type' => [
                                'type' => 'string',
                                'enum' => ['activity', 'laboratory', 'quiz', 'exam'],
                                'description' => 'Type of the assessment.',
                            ],
                            'title' => [
                                'type' => 'string',
                                'description' => 'The title of the assessment (e.g., "Lab Activity 1: Python Loops").',
                            ],
                            'max_points' => [
                                'type' => 'number',
                                'description' => 'Total maximum points for this assessment.',
                            ],
                            'conducted_on' => [
                                'type' => 'string',
                                'description' => 'Scheduled date in YYYY-MM-DD format (or leave empty for today).',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Detailed instructions, problem statements, and rubric criteria.',
                            ],
                            'confirmation_prompt' => [
                                'type' => 'string',
                                'description' => 'The confirmation prompt to present to the teacher (e.g., "Would you like me to add this Lab Activity to CS101?").',
                            ],
                        ],
                        'required' => ['section_id', 'title', 'max_points'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'propose_delete_assessment',
                    'description' => 'Propose deleting an existing assessment. Requires explicit Yes/No teacher confirmation in the chat before any deletion.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the section.',
                            ],
                            'assessment_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the assessment to delete.',
                            ],
                            'title' => [
                                'type' => 'string',
                                'description' => 'The title of the assessment to delete.',
                            ],
                        ],
                        'required' => ['section_id', 'assessment_id', 'title'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call on behalf of the authenticated user.
     *
     * @param string $toolName
     * @param array<string, mixed> $arguments
     * @param User $user
     * @return array{result: mixed, source: array{type: string, title: string, id: ?string, summary: string}}
     * @throws Exception
     */
    public function executeTool(string $toolName, array $arguments, User $user): array
    {
        switch ($toolName) {
            case 'get_portfolio_summary':
                return $this->handlePortfolioSummary($user);

            case 'get_section_summary':
                return $this->handleSectionSummary($user, (int) ($arguments['section_id'] ?? 0));

            case 'get_gradebook_insights':
                return $this->handleGradebookInsights($user, (int) ($arguments['section_id'] ?? 0));

            case 'get_attendance_records':
                return $this->handleAttendanceRecords($user, (int) ($arguments['section_id'] ?? 0));

            case 'get_student_detail':
                return $this->handleStudentDetail($user, (int) ($arguments['section_id'] ?? 0), (string) ($arguments['query'] ?? ''));

            case 'get_course_materials':
                return $this->handleCourseMaterials($user, (int) ($arguments['section_id'] ?? 0));

            case 'get_help_catalog':
                return $this->handleHelpCatalog((string) ($arguments['query'] ?? ''));

            case 'get_assessment_analytics':
                return $this->handleAssessmentAnalytics(
                    $user,
                    (int) ($arguments['section_id'] ?? 0),
                    isset($arguments['assessment_id']) ? (int) $arguments['assessment_id'] : null
                );

            case 'get_at_risk_deficiencies':
                return $this->handleAtRiskDeficiencies($user, (int) ($arguments['section_id'] ?? 0));

            case 'ask_clarification':
                return $this->handleAskClarification($user, $arguments);

            case 'propose_create_assessment':
                return $this->handleProposeCreateAssessment($user, $arguments);

            case 'propose_delete_assessment':
                return $this->handleProposeDeleteAssessment($user, $arguments);

            default:
                throw new Exception("Unknown tool '{$toolName}' requested.", 422);
        }
    }

    /**
     * Verify and resolve section ownership by authenticated user.
     * Supports primary key IDs or subject codes/names (e.g. 1, "IT 101", "BSIT 4A").
     */
    protected function authorizeSection(User $user, int|string $sectionIdentifier): Section
    {
        if (is_numeric($sectionIdentifier) && (int) $sectionIdentifier > 0) {
            $section = Section::where('id', (int) $sectionIdentifier)
                ->where('user_id', $user->id)
                ->first();

            if ($section) {
                return $section;
            }
        }

        // Match by subject_code or name
        $clean = trim((string) $sectionIdentifier);
        if ($clean !== '') {
            $normalized = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($clean));

            $sections = Section::where('user_id', $user->id)->get();
            foreach ($sections as $s) {
                $codeNorm = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($s->subject_code ?? ''));
                $nameNorm = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($s->name ?? ''));

                if ($codeNorm === $normalized || $nameNorm === $normalized || stripos($s->subject_code ?? '', $clean) !== false || stripos($s->name, $clean) !== false) {
                    return $s;
                }
            }
        }

        throw new Exception("No database section found for '{$sectionIdentifier}'. If the teacher is asking to create a syllabus, lesson plan, or activity, please generate the content directly for them.", 404);
    }

    protected function handlePortfolioSummary(User $user): array
    {
        $sections = Section::where('user_id', $user->id)
            ->withCount(['students', 'assessments'])
            ->with('academicTerm')
            ->get();

        $data = [
            'teacher_name' => $user->name,
            'total_sections' => $sections->count(),
            'sections' => $sections->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'subject_code' => $s->subject_code,
                'subject_title' => $s->subject_title,
                'enrolled_students' => $s->students_count,
                'total_assessments' => $s->assessments_count,
                'term' => $s->academicTerm?->name,
                'school_year' => $s->academicTerm?->school_year,
            ])->values()->all(),
        ];

        return [
            'result' => $data,
            'source' => [
                'type' => 'portfolio',
                'title' => 'Teaching Load & Sections Portfolio',
                'id' => null,
                'summary' => "Loaded portfolio of {$sections->count()} handled class sections for {$user->name}.",
            ],
        ];
    }

    protected function handleSectionSummary(User $user, int $sectionId): array
    {
        $section = $this->authorizeSection($user, $sectionId);

        $studentsCount = $section->students()->where('is_active', true)->count();
        $sessionsCount = $section->attendanceSessions()->count();
        $assessmentsCount = $section->assessments()->count();
        $schedules = $section->schedules()->get(['day_of_week', 'starts_at', 'ends_at', 'room']);

        $data = [
            'id' => $section->id,
            'name' => $section->name,
            'subject_code' => $section->subject_code,
            'subject_title' => $section->subject_title,
            'room' => $section->room,
            'enrolled_active_students' => $studentsCount,
            'total_attendance_sessions' => $sessionsCount,
            'total_assessments' => $assessmentsCount,
            'schedules' => $schedules->map(fn ($sch) => "{$sch->day_of_week} {$sch->starts_at}-{$sch->ends_at} ({$sch->room})")->all(),
        ];

        return [
            'result' => $data,
            'source' => [
                'type' => 'section',
                'title' => "Section Summary: {$section->name}",
                'id' => (string) $section->id,
                'summary' => "{$section->name} ({$section->subject_code}) &bull; {$studentsCount} active students.",
            ],
        ];
    }

    protected function handleGradebookInsights(User $user, int $sectionId): array
    {
        $section = $this->authorizeSection($user, $sectionId);
        $insights = $this->gradebookService->getSectionInsights($section);

        return [
            'result' => $insights,
            'source' => [
                'type' => 'gradebook',
                'title' => "Gradebook Insights: {$section->name}",
                'id' => (string) $section->id,
                'summary' => "Authoritative grade calculations: Class Avg: {$insights['class_average_grade']}%, Passing: {$insights['passing_count']}, Failing: {$insights['failing_count']}.",
            ],
        ];
    }

    protected function handleAttendanceRecords(User $user, int $sectionId): array
    {
        $section = $this->authorizeSection($user, $sectionId);

        $sessions = $section->attendanceSessions()->orderBy('session_date')->get();
        $students = $section->students()->where('is_active', true)->orderBy('last_name')->get();

        $absenceMap = [];
        $lateMap = [];

        foreach ($sessions as $session) {
            foreach ($session->records as $r) {
                if ($r->status === AttendanceRecord::STATUS_ABSENT) {
                    $absenceMap[$r->student_id] = ($absenceMap[$r->student_id] ?? 0) + 1;
                } elseif ($r->status === AttendanceRecord::STATUS_LATE) {
                    $lateMap[$r->student_id] = ($lateMap[$r->student_id] ?? 0) + 1;
                }
            }
        }

        $studentAttendance = $students->map(fn ($s) => [
            'student_number' => $s->student_number,
            'full_name' => $s->full_name,
            'absences' => $absenceMap[$s->id] ?? 0,
            'lates' => $lateMap[$s->id] ?? 0,
        ])->values()->all();

        $data = [
            'section_name' => $section->name,
            'total_sessions' => $sessions->count(),
            'students' => $studentAttendance,
        ];

        return [
            'result' => $data,
            'source' => [
                'type' => 'attendance',
                'title' => "Attendance Records: {$section->name}",
                'id' => (string) $section->id,
                'summary' => "Total sessions: {$sessions->count()} &bull; {$students->count()} student attendance ledgers.",
            ],
        ];
    }

    protected function handleStudentDetail(User $user, int $sectionId, string $query): array
    {
        $section = $this->authorizeSection($user, $sectionId);
        $cleanQuery = trim($query);

        if (empty($cleanQuery)) {
            throw new Exception("Student search query cannot be empty.", 422);
        }

        $student = Student::where('section_id', $section->id)
            ->where('is_active', true)
            ->where(function ($q) use ($cleanQuery) {
                $q->where('student_number', 'like', "%{$cleanQuery}%")
                    ->orWhere('last_name', 'like', "%{$cleanQuery}%")
                    ->orWhere('first_name', 'like', "%{$cleanQuery}%");
            })
            ->with(['seat', 'recitations'])
            ->first();

        if (! $student) {
            return [
                'result' => ['error' => "No active student found matching '{$cleanQuery}' in section {$section->name}."],
                'source' => [
                    'type' => 'student',
                    'title' => "Student Search: {$cleanQuery}",
                    'id' => null,
                    'summary' => "No match found in {$section->name}.",
                ],
            ];
        }

        // Fetch scores
        $scores = $student->scores()->with('assessment')->get()->map(fn ($sc) => [
            'task' => $sc->assessment?->title,
            'type' => $sc->assessment?->type,
            'score' => $sc->score,
            'max_points' => $sc->assessment?->max_points,
            'remarks' => $sc->remarks,
        ])->all();

        $absences = AttendanceRecord::where('student_id', $student->id)
            ->where('status', AttendanceRecord::STATUS_ABSENT)
            ->count();

        $data = [
            'id' => $student->id,
            'student_number' => $student->student_number,
            'full_name' => $student->full_name,
            'seat_label' => $student->seat?->label ?: 'Not assigned',
            'absences' => $absences,
            'recitation_points_total' => $student->recitations->sum('points'),
            'scores' => $scores,
        ];

        return [
            'result' => $data,
            'source' => [
                'type' => 'student',
                'title' => "Student Profile: {$student->full_name}",
                'id' => (string) $student->id,
                'summary' => "ID: {$student->student_number} | Seat: {$data['seat_label']} | Absences: {$absences}",
            ],
        ];
    }

    protected function handleCourseMaterials(User $user, int|string $sectionId): array
    {
        try {
            $section = $this->authorizeSection($user, $sectionId);
        } catch (Exception $e) {
            return [
                'result' => [
                    'found' => false,
                    'notice' => "No existing stored modules or syllabus found for '{$sectionId}'. You should draft and generate a complete, high-quality course syllabus outline, module plan, or activity directly for the instructor.",
                ],
                'source' => [
                    'type' => 'course_materials',
                    'title' => "Course Materials: {$sectionId}",
                    'id' => null,
                    'summary' => "No pre-existing database record for {$sectionId}. Ready for generation.",
                ],
            ];
        }

        $assessments = $section->assessments()
            ->orderBy('conducted_on')
            ->orderBy('id')
            ->get();

        $modules = CourseModule::where('section_id', $section->id)
            ->orderBy('order')
            ->get();

        $projects = $section->projects()
            ->orderBy('conducted_on')
            ->get();

        $data = [
            'section_name' => $section->name,
            'subject' => "{$section->subject_code} - {$section->subject_title}",
            'activities_and_assessments' => $assessments->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'number' => $a->assessment_number,
                'title' => $a->title,
                'max_points' => (float) $a->max_points,
                'conducted_on' => $a->conducted_on?->toDateString(),
                'instructions' => $a->description,
                'has_attachment' => ! empty($a->attachment_path),
                'attachment_file_name' => $a->attachment_file_name ?: ($a->attachment_path ? basename($a->attachment_path) : null),
                'attachment_url' => $a->attachment_path ? "/sections/{$section->id}/assessments/{$a->id}/attachment" : null,
            ])->all(),
            'modules_and_topics' => $modules->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'week' => $m->week_number,
                'description' => $m->description,
                'presentation_url' => $m->presentation_url,
                'has_file' => ! empty($m->file_path),
                'file_name' => $m->file_name ?: ($m->file_path ? basename($m->file_path) : null),
            ])->all(),
            'projects_and_group_tasks' => $projects->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'type' => $p->type,
                'max_points' => (float) ($p->max_points ?: 100),
                'has_attachment' => ! empty($p->attachment_path),
                'attachment_file_name' => $p->attachment_file_name ?: ($p->attachment_path ? basename($p->attachment_path) : null),
            ])->all(),
        ];

        return [
            'result' => $data,
            'source' => [
                'type' => 'course_materials',
                'title' => "Curriculum & Activities: {$section->name}",
                'id' => (string) $section->id,
                'summary' => "Loaded {$assessments->count()} assessments/activities, {$modules->count()} course modules, and {$projects->count()} project tasks with attachments.",
            ],
        ];
    }

    protected function handleHelpCatalog(string $query): array
    {
        $results = ClassCheckHelpCatalog::search($query);

        return [
            'result' => $results,
            'source' => [
                'type' => 'help_catalog',
                'title' => 'ClassCheck System Documentation',
                'id' => null,
                'summary' => 'Retrieved verified feature guides and visible button navigation catalog.',
            ],
        ];
    }

    /**
     * Handle proposing creation of an activity/assessment.
     *
     * @param User $user
     * @param array<string, mixed> $args
     * @return array{result: mixed, source: array<string, mixed>}
     */
    protected function handleProposeCreateAssessment(User $user, array $args): array
    {
        $sectionId = (int) ($args['section_id'] ?? 0);
        $section = $this->authorizeSection($user, $sectionId);

        $type = in_array($args['type'] ?? '', ['activity', 'laboratory', 'quiz', 'exam'], true) ? $args['type'] : 'activity';
        $title = trim($args['title'] ?? 'Generated Activity');
        $maxPoints = (float) ($args['max_points'] ?? 100);
        $conductedOn = ! empty($args['conducted_on']) ? $args['conducted_on'] : now()->toDateString();
        $description = $args['description'] ?? null;
        $prompt = $args['confirmation_prompt'] ?? "Would you like to add \"{$title}\" (Max {$maxPoints} pts) to {$section->name}?";

        $proposal = [
            'action' => 'create_assessment',
            'section_id' => $section->id,
            'section_name' => $section->name,
            'type' => $type,
            'title' => $title,
            'max_points' => $maxPoints,
            'conducted_on' => $conductedOn,
            'description' => $description,
            'confirmation_prompt' => $prompt,
        ];

        return [
            'result' => [
                'status' => 'proposal_prepared',
                'proposal' => $proposal,
                'instruction' => "An interactive action card has been attached to the chat. Inform the teacher they can click 'Yes, Add to Class' to immediately save it, 'Edit in Form' to adjust it, or 'No' to dismiss.",
            ],
            'source' => [
                'type' => 'action_proposal',
                'title' => "Proposal: Add {$title}",
                'id' => (string) $section->id,
                'summary' => "Proposed creating {$type} \"{$title}\" ({$maxPoints} pts) in {$section->name}.",
                'proposal' => $proposal,
            ],
        ];
    }

    /**
     * Handle proposing deletion of an assessment.
     *
     * @param User $user
     * @param array<string, mixed> $args
     * @return array{result: mixed, source: array<string, mixed>}
     */
    protected function handleProposeDeleteAssessment(User $user, array $args): array
    {
        $sectionId = (int) ($args['section_id'] ?? 0);
        $section = $this->authorizeSection($user, $sectionId);

        $assessmentId = (int) ($args['assessment_id'] ?? 0);
        $title = trim($args['title'] ?? 'Assessment');

        $proposal = [
            'action' => 'delete_assessment',
            'section_id' => $section->id,
            'section_name' => $section->name,
            'assessment_id' => $assessmentId,
            'title' => $title,
            'confirmation_prompt' => "Are you sure you want to permanently delete \"{$title}\" and all recorded scores from {$section->name}?",
        ];

        return [
            'result' => [
                'status' => 'delete_proposal_prepared',
                'proposal' => $proposal,
                'instruction' => "An interactive confirmation card with 'Yes, Delete' and 'No, Keep Record' has been attached. Deletion will ONLY happen if the teacher explicitly clicks confirm.",
            ],
            'source' => [
                'type' => 'action_proposal',
                'title' => "Proposal: Delete {$title}",
                'id' => (string) $assessmentId,
                'summary' => "Requested deletion confirmation for \"{$title}\" in {$section->name}.",
                'proposal' => $proposal,
            ],
        ];
    }

    /**
     * Handle detailed assessment statistical performance analytics.
     */
    protected function handleAssessmentAnalytics(User $user, int $sectionId, ?int $assessmentId = null): array
    {
        $section = $this->authorizeSection($user, $sectionId);

        $query = $section->assessments()->with('scores');
        if ($assessmentId) {
            $query->where('id', $assessmentId);
        }
        $assessments = $query->orderBy('conducted_on')->get();
        $totalStudents = $section->students()->where('is_active', true)->count();

        $analytics = $assessments->map(function ($a) use ($totalStudents) {
            $scores = $a->scores->pluck('score')->filter(fn ($s) => $s !== null)->values();
            $count = $scores->count();
            $avg = $count > 0 ? round($scores->average(), 2) : 0;
            $max = $count > 0 ? (float) $scores->max() : 0;
            $min = $count > 0 ? (float) $scores->min() : 0;
            $passRate = $count > 0 && (float) $a->max_points > 0
                ? round(($scores->filter(fn ($s) => ((float) $s / (float) $a->max_points) >= 0.75)->count() / $count) * 100, 1)
                : null;

            return [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type,
                'max_points' => (float) $a->max_points,
                'conducted_on' => $a->conducted_on?->toDateString(),
                'graded_submissions' => $count,
                'total_enrolled' => $totalStudents,
                'submission_rate_pct' => $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0,
                'average_score' => $avg,
                'average_pct' => (float) $a->max_points > 0 ? round(($avg / (float) $a->max_points) * 100, 1) : 0,
                'highest_score' => $max,
                'lowest_score' => $min,
                'passing_rate_pct' => $passRate,
            ];
        })->values()->all();

        return [
            'result' => [
                'section_name' => $section->name,
                'assessments_count' => count($analytics),
                'assessments' => $analytics,
            ],
            'source' => [
                'type' => 'assessment_analytics',
                'title' => "Assessment Performance Analytics: {$section->name}",
                'id' => (string) $section->id,
                'summary' => "Statistical analytics for " . count($analytics) . " assessments in {$section->name}.",
            ],
        ];
    }

    /**
     * Handle at-risk student deficiencies and academic intervention analytics.
     */
    protected function handleAtRiskDeficiencies(User $user, int $sectionId): array
    {
        $section = $this->authorizeSection($user, $sectionId);
        $insights = $this->gradebookService->getSectionInsights($section);
        $attendance = $this->handleAttendanceRecords($user, $sectionId)['result'];

        $atRiskStudents = [];
        $absenceLookup = collect($attendance['students'] ?? [])->keyBy('student_number');

        foreach ($insights['at_risk_students'] as $stu) {
            $studentNum = $stu['student_number'] ?? '';
            $att = $absenceLookup->get($studentNum);
            $absences = $att['absences'] ?? 0;

            $triggers = [];
            if (($stu['weighted_grade'] ?? 100) < 75) {
                $triggers[] = "Failing weighted grade ({$stu['weighted_grade']}%)";
            }
            if ($absences >= 3) {
                $triggers[] = "Critical absences ({$absences} absent)";
            } elseif ($absences > 0) {
                $triggers[] = "Absence notice ({$absences} absent)";
            }

            $atRiskStudents[] = [
                'student_number' => $studentNum,
                'full_name' => $stu['full_name'],
                'weighted_grade' => $stu['weighted_grade'],
                'absences' => $absences,
                'lates' => $att['lates'] ?? 0,
                'concerns' => $triggers,
                'recommended_action' => $absences >= 3 ? 'Issue attendance warning slip' : 'Schedule remedial review',
            ];
        }

        return [
            'result' => [
                'section_name' => $section->name,
                'class_average' => $insights['class_average_grade'],
                'at_risk_count' => count($atRiskStudents),
                'at_risk_students' => $atRiskStudents,
            ],
            'source' => [
                'type' => 'deficiencies',
                'title' => "At-Risk & Student Deficiencies: {$section->name}",
                'id' => (string) $section->id,
                'summary' => count($atRiskStudents) . " students identified with academic or attendance concerns.",
            ],
        ];
    }

    /**
     * Handle presenting clarifying questions and interactive choices to the teacher.
     */
    protected function handleAskClarification(User $user, array $args): array
    {
        $question = trim($args['question'] ?? 'Please select an option:');
        $rawOptions = (array) ($args['options'] ?? []);
        $options = array_values(array_filter(array_map('trim', $rawOptions)));
        $isMultiSelect = (bool) ($args['is_multi_select'] ?? false);

        if (empty($options)) {
            $options = ['Yes, Proceed', 'Adjust Details', 'No, Cancel'];
        }

        $choiceCard = [
            'question' => $question,
            'options' => $options,
            'is_multi_select' => $isMultiSelect,
        ];

        return [
            'result' => [
                'status' => 'clarification_presented',
                'question' => $question,
                'options' => $options,
                'instruction' => 'Interactive choice buttons have been rendered in the chat. Inform the teacher they can tap any option or write a custom response.',
            ],
            'source' => [
                'type' => 'clarification',
                'title' => 'Decision Choice Options',
                'id' => null,
                'summary' => "Presented " . count($options) . " choices for: \"{$question}\".",
                'choice_card' => $choiceCard,
            ],
        ];
    }
}
