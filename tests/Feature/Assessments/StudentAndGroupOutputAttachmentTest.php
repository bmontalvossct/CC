<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectGroupMember;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentAndGroupOutputAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Section $section;
    private Student $student;

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

        $this->student = Student::create([
            'section_id' => $this->section->id,
            'student_number' => '2026-0001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'gender' => 'male',
        ]);
    }

    public function test_teacher_can_attach_and_auto_rename_individual_student_assessment_output(): void
    {
        $assessment = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'activity',
            'assessment_number' => '1',
            'title' => 'Laboratory Activity 1',
            'conducted_on' => '2026-09-01',
            'max_points' => 100,
        ]);

        $studentFile = UploadedFile::fake()->create('raw_submission_juan.pdf', 150, 'application/pdf');

        $response = $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/assessments/{$assessment->id}/scores/{$this->student->id}/attachment",
            ['attachment' => $studentFile]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $score = AssessmentScore::where('assessment_id', $assessment->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        // Standardized naming check: Lastname_Activity 1.pdf
        $this->assertStringEndsWith('Dela Cruz_Activity 1.pdf', $score->attachment_name);
        $this->assertNotNull($score->attachment_path);
        Storage::disk('local')->assertExists($score->attachment_path);

        // Download/Stream test
        $streamResponse = $this->actingAs($this->user)->get(
            "/sections/{$this->section->id}/assessments/{$assessment->id}/scores/{$this->student->id}/attachment"
        );
        $streamResponse->assertOk();

        // Delete test
        $oldPath = $score->attachment_path;
        $deleteResponse = $this->actingAs($this->user)->deleteJson(
            "/sections/{$this->section->id}/assessments/{$assessment->id}/scores/{$this->student->id}/attachment"
        );
        $deleteResponse->assertOk();

        $score->refresh();
        $this->assertNull($score->attachment_path);
        $this->assertNull($score->attachment_name);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_teacher_can_attach_and_auto_rename_group_project_output(): void
    {
        $project = Project::create([
            'section_id' => $this->section->id,
            'type' => 'project',
            'title' => 'Final Capstone Project 1',
            'conducted_on' => '2026-11-20',
            'max_points' => 100,
        ]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
            'group_number' => 2,
            'name' => 'Group 2',
        ]);

        $groupFile = UploadedFile::fake()->create('final_code.zip', 500, 'application/zip');

        $response = $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/projects/{$project->id}/groups/{$group->id}/attachment",
            ['attachment' => $groupFile]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $group->refresh();
        // Standardized naming check: Group 2_Project 1.zip
        $this->assertStringEndsWith('Group 2_Project 1.zip', $group->attachment_name);
        $this->assertNotNull($group->attachment_path);
        Storage::disk('local')->assertExists($group->attachment_path);

        // Delete group attachment
        $deleteResponse = $this->actingAs($this->user)->deleteJson(
            "/sections/{$this->section->id}/projects/{$project->id}/groups/{$group->id}/attachment"
        );
        $deleteResponse->assertOk();

        $group->refresh();
        $this->assertNull($group->attachment_path);
        $this->assertNull($group->attachment_name);
    }

    public function test_teacher_can_attach_and_auto_rename_member_output_in_group_project(): void
    {
        $project = Project::create([
            'section_id' => $this->section->id,
            'type' => 'reporting',
            'title' => 'Oral Reporting 2',
            'conducted_on' => '2026-10-10',
            'max_points' => 100,
        ]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
            'group_number' => 1,
            'name' => 'Group 1',
        ]);

        $member = ProjectGroupMember::create([
            'project_group_id' => $group->id,
            'student_id' => $this->student->id,
        ]);

        $memberFile = UploadedFile::fake()->create('slide_presentation.pptx', 200, 'application/vnd.openxmlformats-officedocument.presentationml.presentation');

        $response = $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/projects/{$project->id}/groups/{$group->id}/members/{$this->student->id}/attachment",
            ['attachment' => $memberFile]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $member->refresh();
        // Standardized naming check: Dela Cruz_Report 2.pptx
        $this->assertStringEndsWith('Dela Cruz_Report 2.pptx', $member->attachment_name);
        $this->assertNotNull($member->attachment_path);
        Storage::disk('local')->assertExists($member->attachment_path);

        // Delete member attachment
        $deleteResponse = $this->actingAs($this->user)->deleteJson(
            "/sections/{$this->section->id}/projects/{$project->id}/groups/{$group->id}/members/{$this->student->id}/attachment"
        );
        $deleteResponse->assertOk();

        $member->refresh();
        $this->assertNull($member->attachment_path);
        $this->assertNull($member->attachment_name);
    }
}
