<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectGroupMember;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GroupActivityWorkflowTest extends TestCase
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
            'subject_code' => 'CS101',
            'subject_title' => 'Computer Programming 1',
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

    public function test_teacher_can_create_group_activity_with_randomized_groups(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $this->createStudents($section, 6);

        $response = $this->actingAs($user)->post(route('sections.projects.store', $section), [
            'type' => 'group_activity',
            'title' => 'Hands-on Lab Exercise 1',
            'description' => 'Collaborate in groups of 2 to build a terminal app.',
            'conducted_on' => '2026-08-25',
            'max_points' => 50,
            'group_count' => 3,
            'randomize' => true,
        ]);

        $groupActivity = Project::where('type', 'group_activity')->firstOrFail();
        $response->assertRedirect(route('sections.projects.show', [$section, $groupActivity]));
        $response->assertSessionHas('success', 'Group activity created successfully.');

        $this->assertSame('group_activity', $groupActivity->type);
        $this->assertSame('Hands-on Lab Exercise 1', $groupActivity->title);
        $this->assertSame('Activity 1', $groupActivity->project_number);
        $this->assertEquals(50, $groupActivity->max_points);
        $this->assertSame(3, $groupActivity->groups()->count());

        // Verify that all 6 students were evenly assigned to the 3 groups
        $assignedMemberCount = ProjectGroupMember::whereIn('project_group_id', $groupActivity->groups()->pluck('id'))->count();
        $this->assertSame(6, $assignedMemberCount);
    }

    public function test_group_activity_score_is_calculated_under_activities_category_in_gradebook(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $students = $this->createStudents($section, 2);

        // 1. Create a regular individual Activity (max_points: 20)
        $indivActivity = Assessment::create([
            'section_id' => $section->id,
            'type' => 'activity',
            'title' => 'Individual Worksheet 1',
            'conducted_on' => '2026-08-20',
            'max_points' => '20.00',
        ]);
        AssessmentScore::create([
            'assessment_id' => $indivActivity->id,
            'student_id' => $students[0]->id,
            'score' => '18.00',
        ]);
        AssessmentScore::create([
            'assessment_id' => $indivActivity->id,
            'student_id' => $students[1]->id,
            'score' => '15.00',
        ]);

        // 2. Create a Group Activity (max_points: 30)
        $groupActivity = Project::create([
            'section_id' => $section->id,
            'type' => 'group_activity',
            'project_number' => 'Activity 2',
            'title' => 'Collaborative Group Activity',
            'conducted_on' => '2026-08-22',
            'max_points' => 30,
        ]);

        $group1 = ProjectGroup::create([
            'project_id' => $groupActivity->id,
            'group_number' => 1,
            'name' => 'Group 1',
            'score' => 28, // Group base score = 28 / 30
            'order_column' => 1,
        ]);
        $member1 = ProjectGroupMember::create([
            'project_group_id' => $group1->id,
            'student_id' => $students[0]->id,
            // Inherits group score 28
        ]);

        $group2 = ProjectGroup::create([
            'project_id' => $groupActivity->id,
            'group_number' => 2,
            'name' => 'Group 2',
            'score' => 24, // Group base score = 24
            'order_column' => 2,
        ]);
        $member2 = ProjectGroupMember::create([
            'project_group_id' => $group2->id,
            'student_id' => $students[1]->id,
            'score' => 26, // Individual override = 26
        ]);

        // 3. Create a regular Project (max_points: 100) to confirm separation
        $regularProject = Project::create([
            'section_id' => $section->id,
            'type' => 'project',
            'project_number' => 'Project 1',
            'title' => 'Final Capstone Project',
            'conducted_on' => '2026-08-30',
            'max_points' => 100,
        ]);
        $pGroup = ProjectGroup::create([
            'project_id' => $regularProject->id,
            'group_number' => 1,
            'name' => 'Capstone Team 1',
            'score' => 90,
            'order_column' => 1,
        ]);
        ProjectGroupMember::create(['project_group_id' => $pGroup->id, 'student_id' => $students[0]->id]);
        ProjectGroupMember::create(['project_group_id' => $pGroup->id, 'student_id' => $students[1]->id]);

        // 4. Request Gradebook Report
        $response = $this->actingAs($user)->get(route('sections.reports.gradebook', $section));
        $response->assertOk();

        $response->assertInertia(function (Assert $page) use ($students, $groupActivity, $regularProject) {
            $page->component('reports/Gradebook')
                // Category Summary for 'activity': possible = 20 (indiv) + 30 (group activity) = 50. count = 2
                ->where('categorySummary.activity.possible', 50)
                ->where('categorySummary.activity.count', 2)
                // Project summary should only include regular project (count = 1, possible = 100)
                ->where('projectSummary.possible', 100)
                ->where('projectSummary.count', 1)
                // groupActivities and projects props passed separately
                ->has('groupActivities', 1)
                ->where('groupActivities.0.id', $groupActivity->id)
                ->where('groupActivities.0.type', 'group_activity')
                ->has('projects', 1)
                ->where('projects.0.id', $regularProject->id)
                ->where('projects.0.type', 'project');

            // Check student 0 scores:
            // Activity earned = 18 (indiv) + 28 (group activity) = 46 out of 50 = 92%
            // Project earned = 90 out of 100 = 90%
            $rows = $page->toArray()['props']['rows'];
            $row0 = collect($rows)->firstWhere('id', $students[0]->id);
            $this->assertEquals(46, $row0['categories']['activity']['earned']);
            $this->assertEquals(50, $row0['categories']['activity']['possible']);
            $this->assertEquals(92, $row0['categories']['activity']['percentage']);
            $this->assertEquals(28, $row0['group_activity_scores'][$groupActivity->id]);
            $this->assertEquals(90, $row0['project_scores'][$regularProject->id]);

            // Check student 1 scores:
            // Activity earned = 15 (indiv) + 26 (individual override in group activity) = 41 out of 50 = 82%
            $row1 = collect($rows)->firstWhere('id', $students[1]->id);
            $this->assertEquals(41, $row1['categories']['activity']['earned']);
            $this->assertEquals(50, $row1['categories']['activity']['possible']);
            $this->assertEquals(82, $row1['categories']['activity']['percentage']);
            $this->assertEquals(26, $row1['group_activity_scores'][$groupActivity->id]);
        });
    }

    public function test_gradebook_csv_export_includes_group_activity_under_activity_columns(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $student = $this->createStudents($section, 1)[0];

        // Create Individual Activity (10 pts, scored 9)
        $indiv = Assessment::create([
            'section_id' => $section->id,
            'type' => 'activity',
            'title' => 'Worksheet 1',
            'conducted_on' => '2026-08-20',
            'max_points' => '10.00',
        ]);
        AssessmentScore::create([
            'assessment_id' => $indiv->id,
            'student_id' => $student->id,
            'score' => '9.00',
        ]);

        // Create Group Activity (40 pts, scored 36)
        $groupAct = Project::create([
            'section_id' => $section->id,
            'type' => 'group_activity',
            'project_number' => 'Activity 2',
            'title' => 'Lab Teamwork',
            'conducted_on' => '2026-08-22',
            'max_points' => 40,
        ]);
        $g = ProjectGroup::create([
            'project_id' => $groupAct->id,
            'group_number' => 1,
            'name' => 'Team 1',
            'score' => 36,
            'order_column' => 1,
        ]);
        ProjectGroupMember::create(['project_group_id' => $g->id, 'student_id' => $student->id]);

        $response = $this->actingAs($user)->get(route('sections.exports.gradebook', $section));
        $response->assertOk();
        $content = $response->streamedContent();

        // Check header contains Group Act column
        $this->assertStringContainsString('[Group Act] Activity 2: Lab Teamwork (40.00)', $content);
        // Total Activity Possible = 10 + 40 = 50
        $this->assertStringContainsString('Activity possible', $content);
        $this->assertStringContainsString('50', $content);
        // Total Activity Earned = 9 + 36 = 45 (90%)
        $this->assertStringContainsString('Activity earned', $content);
        $this->assertStringContainsString('45', $content);
        $this->assertStringContainsString('45', $content);
    }
}
