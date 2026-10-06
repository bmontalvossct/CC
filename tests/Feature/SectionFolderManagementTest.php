<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\Project;
use App\Models\Section;
use App\Models\User;
use App\Services\SectionFolderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SectionFolderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function createSection(User $user, array $overrides = []): Section
    {
        $term = AcademicTerm::firstOrCreate([
            'user_id' => $user->id,
            'name' => 'First Semester',
            'school_year' => '2026-2027',
        ], [
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-12-18',
        ]);

        return Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS101',
            'subject_title' => 'Introduction to Computing',
            'name' => 'Section A',
            'enrollment_open' => true,
            ...$overrides,
        ]);
    }

    public function test_section_folder_service_generates_standardized_folder_name(): void
    {
        $service = app(SectionFolderService::class);
        $user = User::factory()->create();

        $section = $this->createSection($user, [
            'subject_code' => 'CS 101',
            'name' => 'Section A (Morning)',
        ]);

        $folderName = $service->getFolderName($section);
        $this->assertSame('CS 101 - Section A (Morning)', $folderName);
    }

    public function test_creating_a_section_automatically_ensures_four_subfolders(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user, [
            'subject_code' => 'IT202',
            'name' => 'BSIT-2A',
        ]);

        $folderName = 'IT202 - BSIT-2A';
        Storage::disk('local')->assertExists("sections/{$folderName}/activities");
        Storage::disk('local')->assertExists("sections/{$folderName}/quiz");
        Storage::disk('local')->assertExists("sections/{$folderName}/report");
        Storage::disk('local')->assertExists("sections/{$folderName}/project");
    }

    public function test_assessment_attachment_is_stored_respectfully_in_activities_or_quiz(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user, [
            'subject_code' => 'MATH101',
            'name' => 'Section 1',
        ]);

        $activityFile = UploadedFile::fake()->create('lab_sheet.pdf', 500, 'application/pdf');
        $this->actingAs($user)->post(route('sections.assessments.store', $section), [
            'type' => 'activity',
            'title' => 'Calculus Activity 1',
            'conducted_on' => '2026-08-10',
            'max_points' => 50,
            'attachment' => $activityFile,
        ])->assertRedirect();

        $assessment1 = Assessment::where('title', 'Calculus Activity 1')->firstOrFail();
        $this->assertStringContainsString('sections/MATH101 - Section 1/activities/', $assessment1->attachment_path);
        Storage::disk('local')->assertExists($assessment1->attachment_path);

        $quizFile = UploadedFile::fake()->create('quiz1_questions.pdf', 300, 'application/pdf');
        $this->actingAs($user)->post(route('sections.assessments.store', $section), [
            'type' => 'quiz',
            'title' => 'Pop Quiz 1',
            'conducted_on' => '2026-08-12',
            'max_points' => 20,
            'attachment' => $quizFile,
        ])->assertRedirect();

        $assessment2 = Assessment::where('title', 'Pop Quiz 1')->firstOrFail();
        $this->assertStringContainsString('sections/MATH101 - Section 1/quiz/', $assessment2->attachment_path);
        Storage::disk('local')->assertExists($assessment2->attachment_path);
    }

    public function test_project_attachment_is_stored_respectfully_in_project_or_report_or_activities(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user, [
            'subject_code' => 'CS301',
            'name' => 'BSCS-3A',
        ]);

        $projectFile = UploadedFile::fake()->create('capstone_guidelines.docx', 800);
        $this->actingAs($user)->post(route('sections.projects.store', $section), [
            'type' => 'project',
            'title' => 'Capstone Project 1',
            'conducted_on' => '2026-09-01',
            'max_points' => 100,
            'attachment' => $projectFile,
        ])->assertRedirect();

        $project = Project::where('title', 'Capstone Project 1')->firstOrFail();
        $this->assertStringContainsString('sections/CS301 - BSCS-3A/project/', $project->attachment_path);
        Storage::disk('local')->assertExists($project->attachment_path);

        $reportFile = UploadedFile::fake()->create('presentation_rubric.pdf', 400, 'application/pdf');
        $this->actingAs($user)->post(route('sections.projects.store', $section), [
            'type' => 'reporting',
            'title' => 'Oral Research Report',
            'conducted_on' => '2026-09-05',
            'max_points' => 50,
            'attachment' => $reportFile,
        ])->assertRedirect();

        $report = Project::where('title', 'Oral Research Report')->firstOrFail();
        $this->assertStringContainsString('sections/CS301 - BSCS-3A/report/', $report->attachment_path);
        Storage::disk('local')->assertExists($report->attachment_path);
    }

    public function test_ensure_section_folders_artisan_command_creates_folders_for_all_existing_sections(): void
    {
        $user = User::factory()->create();

        $this->createSection($user, [
            'subject_code' => 'BIO101',
            'name' => 'BSB-1A',
        ]);

        $this->createSection($user, [
            'subject_code' => 'CHEM101',
            'name' => 'BSB-1B',
        ]);

        $this->artisan('sections:ensure-folders')
            ->assertSuccessful();

        Storage::disk('local')->assertExists('sections/BIO101 - BSB-1A/activities');
        Storage::disk('local')->assertExists('sections/BIO101 - BSB-1A/quiz');
        Storage::disk('local')->assertExists('sections/BIO101 - BSB-1A/report');
        Storage::disk('local')->assertExists('sections/BIO101 - BSB-1A/project');

        Storage::disk('local')->assertExists('sections/CHEM101 - BSB-1B/activities');
        Storage::disk('local')->assertExists('sections/CHEM101 - BSB-1B/quiz');
        Storage::disk('local')->assertExists('sections/CHEM101 - BSB-1B/report');
        Storage::disk('local')->assertExists('sections/CHEM101 - BSB-1B/project');
    }

    public function test_open_section_folder_endpoint_resolves_path(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user, [
            'subject_code' => 'PHYS101',
            'name' => 'BSP-1A',
        ]);

        $response = $this->actingAs($user)->postJson(route('system.open-file-location'), [
            'section_id' => $section->id,
            'category' => 'activities',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
        $this->assertNotEmpty($response->json('path'));
    }
}
