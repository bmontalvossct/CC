<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\CourseModule;
use App\Models\Section;
use App\Models\User;
use App\Services\Autochecker\ExamGeneratorService;
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExamGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Section $section;

    private CourseModule $module1;

    private CourseModule $module2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->teacher = User::factory()->create();

        $term = AcademicTerm::create([
            'user_id' => $this->teacher->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-31',
            'is_current' => true,
        ]);

        $this->section = Section::create([
            'user_id' => $this->teacher->id,
            'academic_term_id' => $term->id,
            'name' => 'BSCS 3A',
            'subject_code' => 'CS 301',
            'subject_title' => 'Software Engineering',
        ]);

        $this->module1 = CourseModule::create([
            'section_id' => $this->section->id,
            'module_number' => 'Module 1',
            'title' => 'Software Development Life Cycle',
            'description' => 'Agile, Scrum, Waterfall, and Sprint Planning.',
            'sort_order' => 1,
        ]);

        $this->module2 = CourseModule::create([
            'section_id' => $this->section->id,
            'module_number' => 'Module 2',
            'title' => 'Architecture & Clean Code Principles',
            'description' => 'SOLID principles, DRY, separation of concerns.',
            'sort_order' => 2,
        ]);
    }

    public function test_unauthorized_user_cannot_access_exam_generator_modules(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->getJson(route('sections.exam-generator.modules', $this->section));

        $response->assertForbidden();
    }

    public function test_teacher_can_fetch_modules_for_exam_generator(): void
    {
        $response = $this->actingAs($this->teacher)
            ->getJson(route('sections.exam-generator.modules', $this->section));

        $response->assertOk();
        $response->assertJsonStructure([
            'modules' => [
                '*' => [
                    'id',
                    'module_number',
                    'title',
                    'description',
                    'has_file',
                ],
            ],
            'section' => ['id', 'name', 'subject_code', 'subject_title'],
        ]);

        $this->assertCount(2, $response->json('modules'));
    }

    public function test_teacher_can_check_hermes_status(): void
    {
        $response = $this->actingAs($this->teacher)
            ->getJson(route('sections.exam-generator.status', $this->section));

        $response->assertOk();
        $response->assertJsonStructure([
            'online',
            'model',
            'is_hermes',
        ]);
    }

    public function test_generation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson(route('sections.exam-generator.generate', $this->section), [
                // Empty payload
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'test_types']);
    }

    public function test_generation_allows_empty_module_ids_with_custom_prompt(): void
    {
        $mockService = $this->createMock(ExamGeneratorService::class);
        $mockService->method('streamGenerateExam')
            ->willReturnCallback(function () {
                yield ['type' => 'status', 'message' => 'Synthesizing prompt...'];
                yield ['type' => 'done', 'student_paper' => 'Test Paper', 'answer_key' => 'Key'];
            });

        $this->app->instance(ExamGeneratorService::class, $mockService);

        $response = $this->actingAs($this->teacher)
            ->post(route('sections.exam-generator.generate', $this->section), [
                'title' => 'Prompt-Based Quiz',
                'instructions' => 'Create a 5-item quiz on OOP concepts.',
                'test_types' => [
                    [
                        'type' => 'identification',
                        'label' => 'Identification',
                        'items_count' => 5,
                        'points_per_item' => 1,
                    ],
                ],
            ]);

        $response->assertOk();
    }

    public function test_generation_rejects_foreign_module_ids(): void
    {
        $foreignSection = Section::create([
            'user_id' => $this->teacher->id,
            'academic_term_id' => $this->section->academic_term_id,
            'name' => 'BSCS 3B',
            'subject_code' => 'CS 302',
            'subject_title' => 'Databases',
        ]);

        $foreignModule = CourseModule::create([
            'section_id' => $foreignSection->id,
            'module_number' => 'Module 99',
            'title' => 'Foreign Material',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->teacher)
            ->postJson(route('sections.exam-generator.generate', $this->section), [
                'title' => 'Midterm Exam',
                'module_ids' => [$foreignModule->id],
                'test_types' => [
                    [
                        'type' => 'identification',
                        'label' => 'Identification',
                        'items_count' => 5,
                        'points_per_item' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('None of the selected course modules were found', $response->json('message'));
    }

    public function test_streaming_generation_returns_streamed_response(): void
    {
        // Mock the ExamGeneratorService generator
        $mockService = $this->createMock(ExamGeneratorService::class);
        $mockService->method('streamGenerateExam')
            ->willReturnCallback(function () {
                yield ['type' => 'status', 'message' => 'Synthesizing curriculum...'];
                yield ['type' => 'delta', 'text' => '=== STUDENT EXAM QUESTIONNAIRE ===\n1. What is Agile?'];
                yield [
                    'type' => 'done',
                    'student_paper' => '1. What is Agile?',
                    'answer_key' => '1. Agile is an iterative approach.',
                    'total_items' => 1,
                    'total_points' => 2,
                ];
            });

        $this->app->instance(ExamGeneratorService::class, $mockService);

        $response = $this->actingAs($this->teacher)
            ->post(route('sections.exam-generator.generate', $this->section), [
                'title' => 'Software Engineering Midterm',
                'module_ids' => [$this->module1->id, $this->module2->id],
                'test_types' => [
                    [
                        'type' => 'identification',
                        'label' => 'Identification',
                        'items_count' => 1,
                        'points_per_item' => 2,
                    ],
                ],
            ]);

        $response->assertOk();
        $this->assertEquals('application/x-ndjson; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function test_teacher_can_save_generated_exam_as_assessment(): void
    {
        $payload = [
            'title' => 'Midterm Examination - Software Engineering',
            'term_period' => 'midterm',
            'conducted_on' => '2026-10-15',
            'max_points' => 50,
            'exam_content' => "=== Part I: Identification ===\n__________ 1. The iterative development framework.\n\n=== Part II: Code Review ===\n1. Review the snippet below and spot the memory leak.",
            'answer_key' => "Part I:\n1. Scrum\n\nPart II:\n1. Event listener was not unbound.",
            'structured_rubric' => [
                'rubric_type' => 'answer_key',
                'items' => [
                    [
                        'id' => 'item_1',
                        'item_number' => 1,
                        'expected_answer' => 'Scrum',
                        'points' => 1.0,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->teacher)
            ->postJson(route('sections.exam-generator.save-assessment', $this->section), $payload);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('assessments', [
            'section_id' => $this->section->id,
            'type' => 'exam',
            'title' => 'Midterm Examination - Software Engineering',
            'term_period' => 'midterm',
            'max_points' => 50.00,
            'rubric_type' => 'answer_key',
        ]);

        $createdAssessment = Assessment::where('section_id', $this->section->id)->where('type', 'exam')->first();
        $this->assertNotNull($createdAssessment);
        $this->assertNotEmpty($createdAssessment->activity_file_path);
        $this->assertEquals('text/markdown', $createdAssessment->activity_file_mime);
        Storage::disk('local')->assertExists($createdAssessment->activity_file_path);
    }

    public function test_exam_generator_prompt_contains_anti_blank_instructions_and_complete_templates(): void
    {
        $service = app(ExamGeneratorService::class);
        $reflector = new \ReflectionClass($service);
        $method = $reflector->getMethod('buildPrompts');
        $method->setAccessible(true);

        $curriculum = $service->extractCurriculumContext(collect([$this->module1, $this->module2]));
        $config = [
            'title' => 'IT 413 - Midterm Examination',
            'term_period' => 'midterm',
            'difficulty' => 'balanced',
            'total_items' => 35,
            'total_points' => 60,
            'test_types' => [
                ['type' => 'identification', 'label' => 'Identification', 'items_count' => 10, 'points_per_item' => 1],
                ['type' => 'enumeration', 'label' => 'Enumeration', 'items_count' => 5, 'points_per_item' => 2],
                ['type' => 'explanation', 'label' => 'Explanation / Essay', 'items_count' => 3, 'points_per_item' => 5],
                ['type' => 'code_review', 'label' => 'Code Review & Analysis', 'items_count' => 2, 'points_per_item' => 5],
                ['type' => 'multiple_choice', 'label' => 'Multiple Choice', 'items_count' => 10, 'points_per_item' => 1],
                ['type' => 'true_false', 'label' => 'True or False', 'items_count' => 5, 'points_per_item' => 1],
            ],
        ];

        $prompts = $method->invoke($service, $this->section, $curriculum, $config);

        $system = $prompts['system'];
        $user = $prompts['user'];

        // Assert anti-blank rules are prominently specified
        $this->assertStringContainsString('ZERO EMPTY QUESTIONS', $system);
        $this->assertStringContainsString('NEVER output an empty blank, a bare number, or a question without its text', $system);
        $this->assertStringContainsString('In IDENTIFICATION: Every single item MUST contain a complete', $system);
        $this->assertStringContainsString('In MULTIPLE CHOICE: Every item MUST have a complete question sentence followed by 4 distinct lettered options', $system);
        $this->assertStringContainsString('In TRUE OR FALSE: Every item MUST have a complete, articulate declarative statement', $system);
        $this->assertStringContainsString('In CODE REVIEW: Code snippets must be realistic, functional code', $system);

        // Assert user prompt reiterates strictness
        $this->assertStringContainsString('Every single question MUST be fully written out', $user);
        $this->assertStringContainsString('NEVER output empty blanks without definitions', $user);
    }
}
