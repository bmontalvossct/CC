<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\Project;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Autochecker\ChatToolRegistry;
use App\Services\Autochecker\OllamaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AutocheckerUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Section $section;

    protected Assessment $assessment;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $term = AcademicTerm::create([
            'user_id' => $this->user->id,
            'name' => '1st Semester 2025-2026',
            'school_year' => '2025-2026',
            'starts_on' => Carbon::parse('2025-08-01'),
            'ends_on' => Carbon::parse('2025-12-15'),
            'is_current' => true,
        ]);

        $this->section = Section::create([
            'user_id' => $this->user->id,
            'academic_term_id' => $term->id,
            'name' => 'BSIT 4A',
            'subject_code' => 'IT401',
            'subject_title' => 'Advanced Web Dev',
        ]);

        $this->student = Student::create([
            'section_id' => $this->section->id,
            'student_number' => '2024-001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'is_active' => true,
        ]);

        $this->assessment = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'activity',
            'assessment_number' => 1,
            'title' => 'Vue Component Lab',
            'max_points' => 100,
            'conducted_on' => Carbon::today(),
        ]);
    }

    public function test_ai_status_and_warm_endpoints()
    {
        $this->actingAs($this->user);

        $statusRes = $this->getJson(route('ai-assistant.status'));
        $statusRes->assertOk();
        $statusRes->assertJsonStructure(['ollama', 'models', 'active_profiles', 'is_local']);

        $warmRes = $this->postJson(route('ai-assistant.warm'));
        $warmRes->assertOk();
        $warmRes->assertJsonStructure(['warmed', 'model']);

        $warmCodeRes = $this->postJson(route('ai-assistant.warm'), ['profile' => 'code_grading']);
        $warmCodeRes->assertOk();
        $warmCodeRes->assertJsonStructure(['warmed', 'model', 'profile']);
        $this->assertEquals('code_grading', $warmCodeRes->json('profile'));

        $warmGeneralRes = $this->postJson(route('ai-assistant.warm'), ['profile' => 'general_grading']);
        $warmGeneralRes->assertOk();
        $warmGeneralRes->assertJsonStructure(['warmed', 'model', 'profile']);
        $this->assertEquals('general_grading', $warmGeneralRes->json('profile'));
    }

    public function test_ai_pull_endpoint_validates_allowed_models()
    {
        $this->actingAs($this->user);

        // Disallowed model should return 422
        $disallowedRes = $this->postJson(route('ai-assistant.pull'), [
            'model' => 'unsupported-malicious-model:latest',
        ]);
        $disallowedRes->assertStatus(422);

        // Allowed hermes model stream should return 200 StreamedResponse
        $allowedRes = $this->post(route('ai-assistant.pull'), [
            'model' => 'hermes3:8b',
        ]);
        $allowedRes->assertOk();
    }

    public function test_chat_stream_rejects_foreign_section_access()
    {
        $otherUser = User::factory()->create();
        $otherTerm = AcademicTerm::create([
            'user_id' => $otherUser->id,
            'name' => 'Term',
            'school_year' => '2025-2026',
            'starts_on' => Carbon::parse('2025-08-01'),
            'ends_on' => Carbon::parse('2025-12-15'),
            'is_current' => true,
        ]);
        $foreignSection = Section::create([
            'user_id' => $otherUser->id,
            'academic_term_id' => $otherTerm->id,
            'name' => 'Foreign Section',
            'subject_code' => 'CS999',
            'subject_title' => 'Other Class',
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson(route('ai-assistant.chat.stream'), [
            'messages' => [
                ['role' => 'user', 'content' => 'What is the average grade?'],
            ],
            'scope' => 'current_section',
            'section_id' => $foreignSection->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_autochecker_inspect_creates_temp_run_and_matches_student()
    {
        $this->actingAs($this->user);

        $dummyCode = "<?php\n// Maria Santos 2024-001\necho 'Hello ClassCheck';\n";
        $file = UploadedFile::fake()->createWithContent('2024-001_santos_lab1.php', $dummyCode);

        $response = $this->postJson(
            route('sections.assessments.autochecker.inspect', [
                'section' => $this->section->id,
                'assessment' => $this->assessment->id,
            ]),
            [
                'files' => [$file],
            ]
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'run_id',
            'items' => [
                '*' => [
                    'item_id',
                    'filename',
                    'sha256',
                    'student_id',
                    'student_name',
                    'preview_lines',
                ],
            ],
            'total_files',
            'matched_count',
        ]);

        $this->assertEquals(1, $response->json('total_files'));
        $this->assertEquals(1, $response->json('matched_count'));
    }

    public function test_autochecker_apply_scores_persists_approved_grades_atomically()
    {
        $this->actingAs($this->user);

        $payload = [
            'run_id' => 'dummy_run_123',
            'scores' => [
                [
                    'student_id' => $this->student->id,
                    'approved' => true,
                    'score' => 95.5,
                    'remarks' => 'Superb component architecture and clean code.',
                    'overwrite_confirmed' => true,
                    'absence_override_confirmed' => true,
                ],
            ],
        ];

        $response = $this->postJson(
            route('sections.assessments.autochecker.apply-scores', [
                'section' => $this->section->id,
                'assessment' => $this->assessment->id,
            ]),
            $payload
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'applied_count' => 1,
        ]);

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $this->assessment->id,
            'student_id' => $this->student->id,
            'score' => 95.5,
            'remarks' => 'Superb component architecture and clean code.',
        ]);
    }

    public function test_ai_assistant_execute_action_creates_assessment_for_authorized_teacher()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('ai-assistant.actions.execute'), [
            'action' => 'create_assessment',
            'section_id' => $this->section->id,
            'type' => 'laboratory',
            'title' => 'Lab Activity 1: Python Loops',
            'max_points' => 50,
            'description' => 'Write a program that demonstrates for-loops.',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'action' => 'create_assessment',
            'assessment' => [
                'title' => 'Lab Activity 1: Python Loops',
                'type' => 'laboratory',
                'max_points' => 50,
            ],
        ]);

        $this->assertDatabaseHas('assessments', [
            'section_id' => $this->section->id,
            'type' => 'laboratory',
            'title' => 'Lab Activity 1: Python Loops',
            'max_points' => 50,
        ]);
    }

    public function test_ai_assistant_execute_action_rejects_unauthorized_teacher()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $response = $this->postJson(route('ai-assistant.actions.execute'), [
            'action' => 'create_assessment',
            'section_id' => $this->section->id,
            'type' => 'activity',
            'title' => 'Unauthorized Activity',
            'max_points' => 100,
        ]);

        $response->assertNotFound();
    }

    public function test_ai_assistant_execute_action_deletes_assessment_when_confirmed()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('ai-assistant.actions.execute'), [
            'action' => 'delete_assessment',
            'section_id' => $this->section->id,
            'assessment_id' => $this->assessment->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'action' => 'delete_assessment',
        ]);

        $this->assertDatabaseMissing('assessments', [
            'id' => $this->assessment->id,
        ]);
    }

    public function test_ai_assistant_suggestions_contain_no_emojis_across_scopes()
    {
        $this->actingAs($this->user);

        // Test app_help scope
        $helpRes = $this->getJson(route('ai-assistant.suggestions', ['scope' => 'app_help']));
        $helpRes->assertOk();
        $helpSuggestions = $helpRes->json('suggestions');
        $this->assertNotEmpty($helpSuggestions);
        foreach ($helpSuggestions as $suggestion) {
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $suggestion);
        }

        // Test current_section scope
        $secRes = $this->getJson(route('ai-assistant.suggestions', [
            'scope' => 'current_section',
            'section_id' => $this->section->id,
        ]));
        $secRes->assertOk();
        $secSuggestions = $secRes->json('suggestions');
        $this->assertNotEmpty($secSuggestions);
        foreach ($secSuggestions as $suggestion) {
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $suggestion);
        }

        // Test global scope
        $globalRes = $this->getJson(route('ai-assistant.suggestions', ['scope' => 'all_classes']));
        $globalRes->assertOk();
        $globalSuggestions = $globalRes->json('suggestions');
        $this->assertNotEmpty($globalSuggestions);
        foreach ($globalSuggestions as $suggestion) {
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $suggestion);
        }
    }

    public function test_chat_tool_registry_ask_clarification_and_analytics()
    {
        $toolRegistry = app(ChatToolRegistry::class);

        // Test ask_clarification tool
        $clarification = $toolRegistry->executeTool('ask_clarification', [
            'question' => 'How would you like to structure this activity?',
            'options' => ['15 Multiple Choice', '10 MC + 2 Coding Problems', 'Custom Rubric'],
        ], $this->user);

        $this->assertEquals('clarification', $clarification['source']['type']);
        $this->assertCount(3, $clarification['source']['choice_card']['options']);
        $this->assertEquals('How would you like to structure this activity?', $clarification['source']['choice_card']['question']);

        // Test get_assessment_analytics tool
        $analytics = $toolRegistry->executeTool('get_assessment_analytics', [
            'section_id' => $this->section->id,
        ], $this->user);

        $this->assertEquals('assessment_analytics', $analytics['source']['type']);
        $this->assertEquals($this->section->name, $analytics['result']['section_name']);

        // Test get_at_risk_deficiencies tool
        $deficiencies = $toolRegistry->executeTool('get_at_risk_deficiencies', [
            'section_id' => $this->section->id,
        ], $this->user);

        $this->assertEquals('deficiencies', $deficiencies['source']['type']);
        $this->assertEquals($this->section->name, $deficiencies['result']['section_name']);
    }

    public function test_chat_stream_accepts_file_attachments()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('ai-assistant.chat.stream'), [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Review this rubric proposal',
                    'attachments' => [
                        [
                            'name' => 'rubric.txt',
                            'content' => 'Criteria: Correctness (50%), Code Style (30%), Testing (20%)',
                        ],
                    ],
                ],
            ],
            'scope' => 'current_section',
            'section_id' => $this->section->id,
        ]);

        $response->assertOk();
    }

    public function test_propose_create_project_groups_tool_and_execution()
    {
        $this->actingAs($this->user);
        $toolRegistry = app(ChatToolRegistry::class);

        // Add 3 more active students to section
        Student::create(['section_id' => $this->section->id, 'student_number' => '2024-002', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'is_active' => true]);
        Student::create(['section_id' => $this->section->id, 'student_number' => '2024-003', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'is_active' => true]);
        Student::create(['section_id' => $this->section->id, 'student_number' => '2024-004', 'first_name' => 'Pedro', 'last_name' => 'Penduko', 'is_active' => true]);

        // 1. Tool execution generates proposal
        $toolOutput = $toolRegistry->executeTool('propose_create_project_groups', [
            'section_id' => $this->section->id,
            'title' => 'Group Activity 1: MVC Architecture',
            'type' => 'group_activity',
            'group_count' => 2,
            'max_points' => 50,
        ], $this->user);

        $this->assertEquals('action_proposal', $toolOutput['source']['type']);
        $proposal = $toolOutput['result']['proposal'];
        $this->assertEquals('create_project_groups', $proposal['action']);
        $this->assertEquals('Group Activity 1: MVC Architecture', $proposal['title']);
        $this->assertEquals(2, $proposal['group_count']);

        // 2. Action execution endpoint executes confirmed proposal
        $executeRes = $this->postJson(route('ai-assistant.actions.execute'), [
            'action' => 'create_project_groups',
            'section_id' => $this->section->id,
            'type' => 'group_activity',
            'title' => 'Group Activity 1: MVC Architecture',
            'group_count' => 2,
            'max_points' => 50,
        ]);

        $executeRes->assertOk();
        $executeRes->assertJson(['success' => true, 'action' => 'create_project_groups']);

        $createdProject = Project::where('section_id', $this->section->id)->where('title', 'Group Activity 1: MVC Architecture')->first();
        $this->assertNotNull($createdProject);
        $this->assertEquals(2, $createdProject->groups()->count());
        $this->assertEquals(4, $createdProject->groups()->withCount('members')->get()->sum('members_count'));
    }

    public function test_create_project_groups_cloning_from_previous_project()
    {
        $this->actingAs($this->user);

        // Create base project with custom groups
        $baseProject = Project::create([
            'section_id' => $this->section->id,
            'type' => 'group_activity',
            'title' => 'Midterm Project',
            'format' => 'group',
        ]);
        $g1 = $baseProject->groups()->create(['group_number' => 1, 'name' => 'Alpha Team', 'order_column' => 1]);
        $g1->members()->create(['student_id' => $this->student->id]);

        $toolRegistry = app(ChatToolRegistry::class);

        // Tool execution referencing source project
        $toolOutput = $toolRegistry->executeTool('propose_create_project_groups', [
            'section_id' => $this->section->id,
            'title' => 'Finals Reporting',
            'type' => 'reporting',
            'source_project_id' => $baseProject->id,
            'copy_names' => true,
        ], $this->user);

        $this->assertEquals($baseProject->id, $toolOutput['result']['proposal']['source_project_id']);
        $this->assertEquals('Midterm Project', $toolOutput['result']['proposal']['source_project_title']);

        // Action execution replicates groups and members
        $executeRes = $this->postJson(route('ai-assistant.actions.execute'), [
            'action' => 'create_project_groups',
            'section_id' => $this->section->id,
            'type' => 'reporting',
            'title' => 'Finals Reporting',
            'source_project_id' => $baseProject->id,
            'copy_names' => true,
        ]);

        $executeRes->assertOk();
        $clonedProject = Project::where('section_id', $this->section->id)->where('title', 'Finals Reporting')->first();
        $this->assertNotNull($clonedProject);
        $this->assertEquals(1, $clonedProject->groups()->count());
        $this->assertEquals('Alpha Team', $clonedProject->groups()->first()->name);
        $this->assertEquals($this->student->id, $clonedProject->groups()->first()->members()->first()->student_id);
    }

    public function test_draft_student_intervention_tool()
    {
        $toolRegistry = app(ChatToolRegistry::class);

        $output = $toolRegistry->executeTool('draft_student_intervention', [
            'section_id' => $this->section->id,
            'student_query' => 'Maria Santos',
            'tone' => 'formal_parent_advisory',
        ], $this->user);

        $this->assertEquals('intervention_draft', $output['source']['type']);
        $this->assertStringContainsString('Santos, Maria', $output['result']['draft']);
        $this->assertStringContainsString('SUMMARY OF ACADEMIC CONCERNS', $output['result']['draft']);
        $this->assertStringContainsString('OFFICIAL PARENT / GUARDIAN ACADEMIC ADVISORY', $output['result']['draft']);
    }

    public function test_recitation_analytics_tool()
    {
        // Add a recitation record
        Recitation::create([
            'section_id' => $this->section->id,
            'student_id' => $this->student->id,
            'score' => 95,
            'conducted_on' => Carbon::today(),
        ]);

        $toolRegistry = app(ChatToolRegistry::class);

        $output = $toolRegistry->executeTool('get_recitation_analytics', [
            'section_id' => $this->section->id,
        ], $this->user);

        $this->assertEquals('recitation_analytics', $output['source']['type']);
        $this->assertEquals(1, $output['result']['total_recitations_recorded']);
        $this->assertEquals(95.0, $output['result']['class_recitation_average']);
        $this->assertEquals(0, $output['result']['uncalled_count']);
    }

    public function test_grading_system_prompt_few_shot_and_citations()
    {
        $ollamaService = app(OllamaService::class);
        $reflection = new \ReflectionClass($ollamaService);
        $method = $reflection->getMethod('buildGradingSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($ollamaService, true);

        $this->assertStringContainsString('evidence_quote', $prompt);
        $this->assertStringContainsString('cite specific line numbers', $prompt);
        $this->assertStringContainsString('FEW-SHOT EXAMPLE', $prompt);
    }

    public function test_propose_create_assessment_returns_yes_no_choice_card_and_generation_instruction()
    {
        $toolRegistry = app(ChatToolRegistry::class);

        $output = $toolRegistry->executeTool('propose_create_assessment', [
            'section_id' => $this->section->id,
            'type' => 'laboratory',
            'title' => 'Binary Search Trees Hands-on Lab',
            'max_points' => 50,
        ], $this->user);

        $this->assertEquals('action_proposal', $output['source']['type']);
        $this->assertNotEmpty($output['source']['choice_card']);
        $this->assertEquals(
            ['Yes, Add as Activity', 'No, Keep as Draft'],
            $output['source']['choice_card']['options']
        );
        $this->assertStringContainsString('WRITE OUT the complete', $output['result']['instruction']);
        $this->assertStringContainsString('Would you like to add this as an activity', $output['result']['instruction']);
    }
}
