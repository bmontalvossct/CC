<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createSection(User $user): Section
    {
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => 'First semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
        ]);

        return Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'IT101',
            'subject_title' => 'Introduction to Computing',
            'name' => 'Section A',
        ]);
    }

    private function createStudents(Section $section, int $count): array
    {
        $students = [];
        for ($i = 1; $i <= $count; $i++) {
            $students[] = Student::create([
                'section_id' => $section->id,
                'student_number' => sprintf('2026-%04d', $i),
                'first_name' => "Student{$i}",
                'last_name' => "LastName{$i}",
                'is_active' => true,
            ]);
        }

        return $students;
    }

    public function test_teacher_can_create_project_and_reporting_activities(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);

        // 1. Create a Project
        $res = $this->actingAs($user)->post(route('sections.projects.store', $section), [
            'type' => 'project',
            'title' => 'Capstone Project',
            'description' => 'Build a responsive web application.',
            'conducted_on' => '2026-08-20',
            'group_count' => 3,
        ]);

        $project = Project::firstOrFail();
        $res->assertRedirect(route('sections.projects.show', [$section, $project]));
        $this->assertSame('project', $project->type);
        $this->assertSame('Capstone Project', $project->title);
        $this->assertSame(3, $project->groups()->count());

        // 2. Create a Reporting Activity
        $res2 = $this->actingAs($user)->post(route('sections.projects.store', $section), [
            'type' => 'reporting',
            'title' => 'Chapter 5 Case Studies',
            'description' => 'Each group will present an assigned topic.',
            'conducted_on' => '2026-08-22',
            'group_count' => 2,
        ]);

        $reporting = Project::where('type', 'reporting')->firstOrFail();
        $res2->assertRedirect(route('sections.projects.show', [$section, $reporting]));
        $this->assertSame(2, $reporting->groups()->count());
    }

    public function test_randomization_algorithm_distributes_uneven_students_with_first_group_having_extra_member(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);

        // Create exactly 21 students
        $this->createStudents($section, 21);

        $project = Project::create([
            'section_id' => $section->id,
            'type' => 'reporting',
            'title' => 'Group Presentation',
        ]);

        // Randomize into 4 groups: 21 / 4 = 5 with remainder 1
        // Group 1 must have 5 + 1 = 6 members
        // Groups 2, 3, 4 must each have 5 members
        $response = $this->actingAs($user)->post(route('sections.projects.randomize', [$section, $project]), [
            'group_count' => 4,
        ]);

        $response->assertSessionHas('success');

        $groups = $project->groups()->with('members')->orderBy('group_number')->get();
        $this->assertCount(4, $groups);

        $this->assertSame(1, $groups[0]->group_number);
        $this->assertSame(6, $groups[0]->members->count(), 'Group 1 (first on list) must have 1 additional member when uneven');

        $this->assertSame(5, $groups[1]->members->count());
        $this->assertSame(5, $groups[2]->members->count());
        $this->assertSame(5, $groups[3]->members->count());

        // Total members across all groups = 21
        $totalMembers = $groups->sum(fn ($g) => $g->members->count());
        $this->assertSame(21, $totalMembers);

        // Test with remainder = 2 (e.g. 22 students in 4 groups -> 6, 6, 5, 5)
        Student::create([
            'section_id' => $section->id,
            'student_number' => '2026-0022',
            'first_name' => 'Student22',
            'last_name' => 'LastName22',
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('sections.projects.randomize', [$section, $project]), [
            'group_count' => 4,
        ]);

        $groups2 = $project->groups()->with('members')->orderBy('group_number')->get();
        $this->assertSame(6, $groups2[0]->members->count(), 'Group 1 has extra member');
        $this->assertSame(6, $groups2[1]->members->count(), 'Group 2 has extra member');
        $this->assertSame(5, $groups2[2]->members->count());
        $this->assertSame(5, $groups2[3]->members->count());
    }

    public function test_teacher_can_manually_update_topic_for_reporting_group(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $this->createStudents($section, 10);

        $project = Project::create([
            'section_id' => $section->id,
            'type' => 'reporting',
            'title' => 'Science Seminars',
        ]);

        $group1 = $project->groups()->create([
            'group_number' => 1,
            'name' => 'Group 1',
            'order_column' => 1,
        ]);

        // Update topic manually
        $response = $this->actingAs($user)->patchJson(
            route('sections.projects.groups.update', [$section, $project, $group1]),
            [
                'topic' => 'Quantum Computing and Superposition',
            ]
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame('Quantum Computing and Superposition', $group1->fresh()->topic);
    }

    public function test_teacher_can_add_remove_and_move_group_members(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $students = $this->createStudents($section, 3);

        $project = Project::create([
            'section_id' => $section->id,
            'type' => 'project',
            'title' => 'Software Project',
        ]);

        $group1 = $project->groups()->create(['group_number' => 1, 'name' => 'Group 1', 'order_column' => 1]);
        $group2 = $project->groups()->create(['group_number' => 2, 'name' => 'Group 2', 'order_column' => 2]);

        // Add Student 1 to Group 1
        $this->actingAs($user)->post(route('sections.projects.groups.members.store', [$section, $project, $group1]), [
            'student_id' => $students[0]->id,
        ]);
        $this->assertSame(1, $group1->members()->count());

        // Move Student 1 from Group 1 to Group 2
        $this->actingAs($user)->post(route('sections.projects.members.move', [$section, $project]), [
            'student_id' => $students[0]->id,
            'target_group_id' => $group2->id,
        ]);

        $this->assertSame(0, $group1->members()->count());
        $this->assertSame(1, $group2->members()->count());

        // Remove Student 1 from Group 2
        $this->actingAs($user)->delete(
            route('sections.projects.groups.members.destroy', [$section, $project, $group2, $students[0]])
        );
        $this->assertSame(0, $group2->members()->count());
    }

    public function test_teacher_cannot_access_or_modify_another_teachers_projects(): void
    {
        $teacher1 = User::factory()->create();
        $teacher2 = User::factory()->create();

        $section1 = $this->createSection($teacher1);
        $project1 = Project::create([
            'section_id' => $section1->id,
            'type' => 'project',
            'title' => 'Private Project',
        ]);

        // Teacher 2 attempts to view Teacher 1's project
        $this->actingAs($teacher2)->get(route('sections.projects.show', [$section1, $project1]))
            ->assertForbidden();

        // Teacher 2 attempts to randomize Teacher 1's project
        $this->actingAs($teacher2)->post(route('sections.projects.randomize', [$section1, $project1]), ['group_count' => 2])
            ->assertForbidden();
    }

    public function test_printable_group_sheet_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $this->createStudents($section, 6);

        $project = Project::create([
            'section_id' => $section->id,
            'type' => 'reporting',
            'title' => 'Final Presentation',
        ]);

        $this->actingAs($user)->post(route('sections.projects.randomize', [$section, $project]), [
            'group_count' => 2,
        ]);

        $this->actingAs($user)->get(route('sections.projects.print', [$section, $project]))
            ->assertOk();
    }

    public function test_teacher_can_create_and_download_project_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $file = UploadedFile::fake()->create('project_rubric.pdf', 1500, 'application/pdf');

        $res = $this->actingAs($user)->post(route('sections.projects.store', $section), [
            'type' => 'project',
            'title' => 'Software Engineering Project',
            'description' => 'Follow the guidelines and rubric in the attached file.',
            'conducted_on' => '2026-08-25',
            'max_points' => 100,
            'group_count' => 3,
            'attachment' => $file,
        ]);

        $project = Project::where('title', 'Software Engineering Project')->firstOrFail();
        $res->assertRedirect(route('sections.projects.show', [$section, $project]));
        $this->assertNotNull($project->attachment_path);
        $this->assertSame('project_rubric.pdf', $project->attachment_name);
        Storage::disk('local')->assertExists($project->attachment_path);

        $this->actingAs($user)->get(route('sections.projects.attachment', [$section, $project]))
            ->assertOk();
    }

    public function test_teacher_can_update_project_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $initialFile = UploadedFile::fake()->create('initial_guide.docx', 500);

        $project = Project::create([
            'section_id' => $section->id,
            'type' => 'reporting',
            'title' => 'Research Reporting',
            'attachment_path' => $initialFile->store("projects/{$section->id}", 'local'),
            'attachment_name' => 'initial_guide.docx',
            'attachment_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);

        $newFile = UploadedFile::fake()->create('revised_rubric.pdf', 800, 'application/pdf');

        $this->actingAs($user)->put(route('sections.projects.update', [$section, $project]), [
            'title' => 'Research Reporting Revised',
            'type' => 'reporting',
            'attachment' => $newFile,
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame('Research Reporting Revised', $project->title);
        $this->assertSame('revised_rubric.pdf', $project->attachment_name);
        Storage::disk('local')->assertExists($project->attachment_path);
    }

    public function test_teacher_can_delete_project_and_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $file = UploadedFile::fake()->create('project_guidelines.pdf', 500, 'application/pdf');

        $project = Project::create([
            'section_id' => $section->id,
            'type' => 'project',
            'title' => 'Project To Delete',
            'attachment_path' => $file->store("projects/{$section->id}", 'local'),
            'attachment_name' => 'project_guidelines.pdf',
        ]);

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        Storage::disk('local')->assertExists($project->attachment_path);

        $response = $this->actingAs($user)->delete(route('sections.projects.destroy', [$section, $project]));

        $response->assertRedirect(route('sections.assessments.index', $section))
            ->assertSessionHas('success', 'Project deleted.');

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        Storage::disk('local')->assertMissing($project->attachment_path);
    }
}
