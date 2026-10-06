<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\Project;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $this->user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-31',
            'is_current' => true,
        ]);

        $this->section = Section::create([
            'user_id' => $this->user->id,
            'academic_term_id' => $term->id,
            'name' => 'BSIT 3A',
            'subject_code' => 'IT 311',
            'subject_title' => 'Web Systems & Technologies',
        ]);
    }

    public function test_teacher_can_reupload_activity_attachment(): void
    {
        $initialFile = UploadedFile::fake()->create('activity_instructions.pdf', 100, 'application/pdf');
        
        $response = $this->actingAs($this->user)->post("/sections/{$this->section->id}/assessments", [
            'type' => 'activity',
            'title' => 'Laboratory Activity 1',
            'conducted_on' => '2026-09-01',
            'max_points' => 100,
            'attachment' => $initialFile,
        ]);

        $response->assertRedirect();
        $assessment = Assessment::where('section_id', $this->section->id)->firstOrFail();
        $this->assertNotNull($assessment->attachment_path);
        $this->assertEquals('Activity_details 1.pdf', $assessment->attachment_name);
        Storage::disk('local')->assertExists($assessment->attachment_path);

        $oldPath = $assessment->attachment_path;

        // Reupload / Replace with a new file
        $newFile = UploadedFile::fake()->create('updated_activity_rubric.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $reuploadResponse = $this->actingAs($this->user)->post("/sections/{$this->section->id}/assessments/{$assessment->id}/attachment", [
            'attachment' => $newFile,
        ]);

        $reuploadResponse->assertRedirect();
        $assessment->refresh();

        $this->assertEquals('Activity_details 1.docx', $assessment->attachment_name);
        Storage::disk('local')->assertExists($assessment->attachment_path);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_teacher_can_delete_assessment_attachment(): void
    {
        $file = UploadedFile::fake()->create('quiz_sheet.pdf', 50, 'application/pdf');

        $this->actingAs($this->user)->post("/sections/{$this->section->id}/assessments", [
            'type' => 'quiz',
            'title' => 'Quiz 1',
            'conducted_on' => '2026-09-05',
            'max_points' => 50,
            'attachment' => $file,
        ]);

        $assessment = Assessment::where('section_id', $this->section->id)->firstOrFail();
        $this->assertEquals('Quiz_details 1.pdf', $assessment->attachment_name);
        $path = $assessment->attachment_path;
        Storage::disk('local')->assertExists($path);

        // Delete attachment via DELETE endpoint
        $response = $this->actingAs($this->user)->delete("/sections/{$this->section->id}/assessments/{$assessment->id}/attachment");
        $response->assertRedirect();

        $assessment->refresh();
        $this->assertNull($assessment->attachment_path);
        $this->assertNull($assessment->attachment_name);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_teacher_can_remove_assessment_attachment_via_update_form(): void
    {
        $file = UploadedFile::fake()->create('exam_questions.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)->post("/sections/{$this->section->id}/assessments", [
            'type' => 'exam',
            'title' => 'Midterm Major Exam',
            'conducted_on' => '2026-10-15',
            'max_points' => 100,
            'attachment' => $file,
        ]);

        $assessment = Assessment::where('section_id', $this->section->id)->firstOrFail();
        $path = $assessment->attachment_path;
        Storage::disk('local')->assertExists($path);

        // Update with remove_attachment = true
        $response = $this->actingAs($this->user)->put("/sections/{$this->section->id}/assessments/{$assessment->id}", [
            'type' => 'exam',
            'title' => 'Midterm Major Exam',
            'conducted_on' => '2026-10-15',
            'max_points' => 100,
            'remove_attachment' => true,
        ]);

        $response->assertRedirect();
        $assessment->refresh();
        $this->assertNull($assessment->attachment_path);
        $this->assertNull($assessment->attachment_name);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_teacher_can_reupload_project_and_report_attachment(): void
    {
        $initialFile = UploadedFile::fake()->create('report_guidelines.pdf', 150, 'application/pdf');

        $this->actingAs($this->user)->post("/sections/{$this->section->id}/projects", [
            'type' => 'reporting',
            'title' => 'Chapter 1 Oral Reporting',
            'conducted_on' => '2026-09-10',
            'max_points' => 100,
            'group_count' => 4,
            'attachment' => $initialFile,
        ]);

        $project = Project::where('section_id', $this->section->id)->firstOrFail();
        $this->assertEquals('Report_details 1.pdf', $project->attachment_name);
        $oldPath = $project->attachment_path;
        Storage::disk('local')->assertExists($oldPath);

        // Reupload new presentation guidelines
        $newFile = UploadedFile::fake()->create('revised_rubric.pptx', 300, 'application/vnd.openxmlformats-officedocument.presentationml.presentation');

        $reuploadResponse = $this->actingAs($this->user)->post("/sections/{$this->section->id}/projects/{$project->id}/attachment", [
            'attachment' => $newFile,
        ]);

        $reuploadResponse->assertRedirect();
        $project->refresh();

        $this->assertEquals('Report_details 1.pptx', $project->attachment_name);
        Storage::disk('local')->assertExists($project->attachment_path);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_teacher_can_delete_project_attachment(): void
    {
        $file = UploadedFile::fake()->create('project_brief.pdf', 80, 'application/pdf');

        $this->actingAs($this->user)->post("/sections/{$this->section->id}/projects", [
            'type' => 'project',
            'title' => 'Capstone Term Project',
            'conducted_on' => '2026-11-01',
            'max_points' => 100,
            'group_count' => 5,
            'attachment' => $file,
        ]);

        $project = Project::where('section_id', $this->section->id)->firstOrFail();
        $path = $project->attachment_path;
        Storage::disk('local')->assertExists($path);

        // Delete attachment via DELETE endpoint
        $response = $this->actingAs($this->user)->delete("/sections/{$this->section->id}/projects/{$project->id}/attachment");
        $response->assertRedirect();

        $project->refresh();
        $this->assertNull($project->attachment_path);
        $this->assertNull($project->attachment_name);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_teacher_can_remove_project_attachment_via_update_form(): void
    {
        $file = UploadedFile::fake()->create('activity_template.zip', 250, 'application/zip');

        $this->actingAs($this->user)->post("/sections/{$this->section->id}/projects", [
            'type' => 'group_activity',
            'title' => 'Collaborative Case Study',
            'conducted_on' => '2026-09-15',
            'max_points' => 100,
            'group_count' => 3,
            'attachment' => $file,
        ]);

        $project = Project::where('section_id', $this->section->id)->firstOrFail();
        $path = $project->attachment_path;
        Storage::disk('local')->assertExists($path);

        // Update project with remove_attachment = true
        $response = $this->actingAs($this->user)->put("/sections/{$this->section->id}/projects/{$project->id}", [
            'type' => 'group_activity',
            'title' => 'Collaborative Case Study',
            'conducted_on' => '2026-09-15',
            'max_points' => 100,
            'group_count' => 3,
            'remove_attachment' => true,
        ]);

        $response->assertRedirect();
        $project->refresh();
        $this->assertNull($project->attachment_path);
        $this->assertNull($project->attachment_name);
        Storage::disk('local')->assertMissing($path);
    }
}
