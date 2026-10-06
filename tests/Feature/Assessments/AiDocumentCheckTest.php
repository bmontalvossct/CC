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

class AiDocumentCheckTest extends TestCase
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

        $mockGrader = $this->createMock(\App\Services\Autochecker\AiDocumentGraderService::class);
        $mockGrader->method('gradeAssessmentSubmission')->willReturn([
            'score' => 95.0,
            'remarks' => 'Clean form structure with proper semantic HTML tags.',
            'student_id' => $this->student->id,
        ]);
        $mockGrader->method('gradeProjectGroupSubmission')->willReturn([
            'score' => 92.0,
            'remarks' => 'Solid collaborative implementation.',
            'group_id' => 1,
        ]);
        $mockGrader->method('gradeProjectMemberSubmission')->willReturn([
            'score' => 45.0,
            'remarks' => 'Clear presentation slides.',
            'student_id' => $this->student->id,
        ]);
        $this->app->instance(\App\Services\Autochecker\AiDocumentGraderService::class, $mockGrader);
    }

    public function test_ai_check_scores_and_remarks_for_assessment_submission(): void
    {
        $assessment = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'activity',
            'assessment_number' => '1',
            'title' => 'Laboratory Activity 1 - HTML Forms',
            'description' => 'Create a user registration form with validation and semantic HTML.',
            'conducted_on' => '2026-09-01',
            'max_points' => 100,
        ]);

        $studentDoc = UploadedFile::fake()->createWithContent(
            'registration_submission.txt',
            "Laboratory 1: Registration Form\nStudent: Juan Dela Cruz\nCreated form with input fields, labels, and validation."
        );

        $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/assessments/{$assessment->id}/scores/{$this->student->id}/attachment",
            ['attachment' => $studentDoc]
        )->assertOk();

        $response = $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/assessments/{$assessment->id}/scores/{$this->student->id}/ai-check"
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'student_id',
            'score',
            'remarks',
            'saved_at',
        ]);
        $this->assertTrue($response->json('success'));

        $scoreRecord = AssessmentScore::where('assessment_id', $assessment->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        $this->assertNotNull($scoreRecord->score);
        $this->assertNotNull($scoreRecord->remarks);
        $this->assertGreaterThanOrEqual(0, (float) $scoreRecord->score);
        $this->assertLessThanOrEqual(100, (float) $scoreRecord->score);
        $this->assertNotEmpty($scoreRecord->remarks);

        // Teacher can still alter the score and remarks manually afterwards
        $updateResponse = $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/assessments/{$assessment->id}/scores/batch",
            [
                'scores' => [$this->student->id => 98],
                'remarks' => [$this->student->id => 'Teacher adjusted score after review: Excellent structure.'],
                'include_absent' => false,
            ]
        );
        $updateResponse->assertOk();

        $scoreRecord->refresh();
        $this->assertEquals(98, (float) $scoreRecord->score);
        $this->assertEquals('Teacher adjusted score after review: Excellent structure.', $scoreRecord->remarks);
    }

    public function test_ai_check_fails_gracefully_if_no_attachment_present(): void
    {
        $assessment = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'activity',
            'assessment_number' => '1',
            'title' => 'Laboratory Activity 1',
            'conducted_on' => '2026-09-01',
            'max_points' => 100,
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/assessments/{$assessment->id}/scores/{$this->student->id}/ai-check"
        );

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'No student output attached to evaluate.',
        ]);
    }

    public function test_ai_check_scores_and_notes_for_project_group_submission(): void
    {
        $project = Project::create([
            'section_id' => $this->section->id,
            'type' => 'project',
            'format' => 'group',
            'title' => 'Final Capstone Project Proposal',
            'description' => 'Submit comprehensive project documentation with problem statement, methodology, and scope.',
            'conducted_on' => '2026-09-01',
            'max_points' => 100,
        ]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
            'group_number' => 1,
            'name' => 'Group 1 - CyberGuardians',
            'topic' => 'School RFID Attendance System',
            'description' => 'An automated RFID attendance system with web portal.',
            'order_column' => 1,
        ]);

        ProjectGroupMember::create([
            'project_group_id' => $group->id,
            'student_id' => $this->student->id,
        ]);

        $groupFile = UploadedFile::fake()->createWithContent(
            'group_1_proposal.txt',
            "Capstone Proposal: School RFID Attendance System\nGroup 1\nExecutive Summary, Architecture, and Testing Plan."
        );

        $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/projects/{$project->id}/groups/{$group->id}/attachment",
            ['attachment' => $groupFile]
        )->assertOk();

        $response = $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/projects/{$project->id}/groups/{$group->id}/ai-check"
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'group_id',
            'score',
            'notes',
        ]);
        $this->assertTrue($response->json('success'));

        $group->refresh();
        $this->assertNotNull($group->score);
        $this->assertNotNull($group->notes);
        $this->assertGreaterThanOrEqual(0, (float) $group->score);
        $this->assertLessThanOrEqual(100, (float) $group->score);
    }

    public function test_ai_check_scores_and_notes_for_project_member_submission(): void
    {
        $project = Project::create([
            'section_id' => $this->section->id,
            'type' => 'reporting',
            'format' => 'individual',
            'title' => 'Oral Reporting on Modern Web Frameworks',
            'description' => 'Individual report and submission on Vue.js and React.',
            'conducted_on' => '2026-09-01',
            'max_points' => 50,
        ]);

        $group = ProjectGroup::create([
            'project_id' => $project->id,
            'group_number' => 1,
            'name' => 'Presenter 1',
            'topic' => 'Vue 3 Composition API Deep Dive',
            'order_column' => 1,
        ]);

        $member = ProjectGroupMember::create([
            'project_group_id' => $group->id,
            'student_id' => $this->student->id,
        ]);

        $memberFile = UploadedFile::fake()->createWithContent(
            'juan_reporting_slides.txt',
            "Vue 3 Composition API\nPresenter: Juan Dela Cruz\nReactivity system, ref, reactive, computed, and lifecycle hooks."
        );

        $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/projects/{$project->id}/groups/{$group->id}/members/{$this->student->id}/attachment",
            ['attachment' => $memberFile]
        )->assertOk();

        $response = $this->actingAs($this->user)->postJson(
            "/sections/{$this->section->id}/projects/{$project->id}/groups/{$group->id}/members/{$this->student->id}/ai-check"
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'member_id',
            'student_id',
            'score',
            'notes',
        ]);
        $this->assertTrue($response->json('success'));

        $member->refresh();
        $this->assertNotNull($member->score);
        $this->assertNotNull($member->notes);
        $this->assertGreaterThanOrEqual(0, (float) $member->score);
        $this->assertLessThanOrEqual(50, (float) $member->score);
    }
}
