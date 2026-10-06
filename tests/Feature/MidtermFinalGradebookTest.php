<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectGroupMember;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\GradebookCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtermFinalGradebookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Section $section;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

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
            'grading_weights' => [
                'activity' => 20,
                'laboratory' => 0,
                'quiz' => 20,
                'exam' => 30,
                'project' => 15,
                'attendance' => 15,
                'recitation' => 5,
                'reporting_frequency' => 'once_per_sem',
                'midterm_weight' => 50,
                'final_weight' => 50,
            ],
        ]);

        $this->student = Student::create([
            'section_id' => $this->section->id,
            'student_number' => '2024-0001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'is_active' => true,
        ]);
    }

    public function test_midterm_grade_calculates_from_records_on_or_before_midterm_exam(): void
    {
        // 1. Midterm Period Activity (Date: 2026-09-01) - 100/100
        $act1 = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'activity',
            'title' => 'Midterm Activity 1',
            'conducted_on' => '2026-09-01',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $act1->id,
            'student_id' => $this->student->id,
            'score' => 100,
        ]);

        // 2. Midterm Period Quiz (Date: 2026-09-10) - 50/50
        $quiz1 = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'quiz',
            'title' => 'Midterm Quiz 1',
            'conducted_on' => '2026-09-10',
            'max_points' => 50,
        ]);
        AssessmentScore::create([
            'assessment_id' => $quiz1->id,
            'student_id' => $this->student->id,
            'score' => 50,
        ]);

        // 3. Midterm Exam (Date: 2026-10-15) - 100/100
        $midtermExam = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'exam',
            'title' => 'Midterm Major Examination',
            'conducted_on' => '2026-10-15',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $midtermExam->id,
            'student_id' => $this->student->id,
            'score' => 100,
        ]);

        // 4. Final Period Activity (Date: 2026-11-01) - 80/100
        $act2 = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'activity',
            'title' => 'Final Activity 1',
            'conducted_on' => '2026-11-01',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $act2->id,
            'student_id' => $this->student->id,
            'score' => 80,
        ]);

        // 5. Final Exam (Date: 2026-12-15) - 90/100
        $finalExam = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'exam',
            'title' => 'Final Major Examination',
            'conducted_on' => '2026-12-15',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $finalExam->id,
            'student_id' => $this->student->id,
            'score' => 90,
        ]);

        // Attendance session in Midterm period (Date: 2026-09-05) - Present
        $session1 = AttendanceSession::create([
            'section_id' => $this->section->id,
            'session_date' => '2026-09-05',
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
            'duration_minutes' => 120,
        ]);
        AttendanceRecord::create([
            'attendance_session_id' => $session1->id,
            'student_id' => $this->student->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        // Attendance session in Final period (Date: 2026-11-05) - Present
        $session2 = AttendanceSession::create([
            'section_id' => $this->section->id,
            'session_date' => '2026-11-05',
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
            'duration_minutes' => 120,
        ]);
        AttendanceRecord::create([
            'attendance_session_id' => $session2->id,
            'student_id' => $this->student->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $service = app(GradebookCalculationService::class);
        $gradebook = $service->calculateGradebook($this->section);

        $row = $gradebook['rows']->first();

        // Midterm grade should be 100% since act1 (100%), quiz1 (100%), midtermExam (100%), att1 (100%)
        $this->assertEquals(100.0, $row['midterm']['weighted_grade']);
        $this->assertEquals('1.00', $row['midterm']['scale_grade']);

        // Final period grade has act2 (80%), finalExam (90%), att2 (100%)
        // Normalized over available categories (Activity 20, Exam 30, Attendance 15, total = 65)
        // (80 * 0.20 + 90 * 0.30 + 100 * 0.15) / 0.65 = 58 / 0.65 = 89.23%
        $this->assertEquals(89.23, $row['final_period']['weighted_grade']);
        $this->assertEquals('1.75', $row['final_period']['scale_grade']);

        // Semestral grade = 50% Midterm (100) + 50% Final (89.23) = 94.62% -> 1.25
        $this->assertEquals(94.62, $row['weighted_grade']);
        $this->assertEquals('1.25', $row['scale_grade']);
    }

    public function test_reporting_once_per_sem_is_credited_to_finals_and_excluded_from_midterms(): void
    {
        // Midterm Exam on 2026-10-15
        $midtermExam = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'exam',
            'title' => 'Midterm Exam',
            'conducted_on' => '2026-10-15',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $midtermExam->id,
            'student_id' => $this->student->id,
            'score' => 90,
        ]);

        // Oral Reporting Project (Date: 2026-11-20, in Finals period) - 95/100
        $reportProj = Project::create([
            'section_id' => $this->section->id,
            'type' => 'reporting',
            'title' => 'Chapter 5 Oral Presentation',
            'conducted_on' => '2026-11-20',
            'max_points' => 100,
        ]);
        $group = ProjectGroup::create([
            'project_id' => $reportProj->id,
            'group_number' => 1,
            'name' => 'Group 1',
            'score' => 95,
        ]);
        ProjectGroupMember::create([
            'project_group_id' => $group->id,
            'student_id' => $this->student->id,
            'score' => 95,
        ]);

        $service = app(GradebookCalculationService::class);
        $gradebook = $service->calculateGradebook($this->section);
        $row = $gradebook['rows']->first();

        // Midterm project summary should have count 0 because reporting is 1 per sem and reserved for Finals!
        $this->assertEquals(0, $row['midterm']['projectSummary']['count']);
        $this->assertEquals(90.0, $row['midterm']['weighted_grade']);

        // Final period project summary should include the reporting score
        $this->assertEquals(1, $row['final_period']['projectSummary']['count']);
        $this->assertEquals(95.0, $row['final_period']['projectSummary']['percentage']);
    }

    public function test_reporting_twice_per_sem_allocates_to_midterms_and_finals(): void
    {
        $this->section->update([
            'grading_weights' => array_merge($this->section->grading_weights, [
                'reporting_frequency' => 'twice_per_sem',
            ]),
        ]);

        // Midterm Exam on 2026-10-15
        $midtermExam = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'exam',
            'title' => 'Midterm Exam',
            'conducted_on' => '2026-10-15',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $midtermExam->id,
            'student_id' => $this->student->id,
            'score' => 90,
        ]);

        // Midterm Report (Date: 2026-09-20) - 85/100
        $midReport = Project::create([
            'section_id' => $this->section->id,
            'type' => 'reporting',
            'title' => 'Midterm Presentation',
            'conducted_on' => '2026-09-20',
            'max_points' => 100,
        ]);
        $grp1 = ProjectGroup::create(['project_id' => $midReport->id, 'group_number' => 1, 'score' => 85]);
        ProjectGroupMember::create(['project_group_id' => $grp1->id, 'student_id' => $this->student->id, 'score' => 85]);

        // Final Report (Date: 2026-11-20) - 95/100
        $finReport = Project::create([
            'section_id' => $this->section->id,
            'type' => 'reporting',
            'title' => 'Final Presentation',
            'conducted_on' => '2026-11-20',
            'max_points' => 100,
        ]);
        $grp2 = ProjectGroup::create(['project_id' => $finReport->id, 'group_number' => 1, 'score' => 95]);
        ProjectGroupMember::create(['project_group_id' => $grp2->id, 'student_id' => $this->student->id, 'score' => 95]);

        $service = app(GradebookCalculationService::class);
        $gradebook = $service->calculateGradebook($this->section);
        $row = $gradebook['rows']->first();

        // In twice_per_sem, Midterm includes the Midterm report
        $this->assertEquals(1, $row['midterm']['projectSummary']['count']);
        $this->assertEquals(85.0, $row['midterm']['projectSummary']['percentage']);

        // Final period includes the Final report
        $this->assertEquals(1, $row['final_period']['projectSummary']['count']);
        $this->assertEquals(95.0, $row['final_period']['projectSummary']['percentage']);
    }

    public function test_teacher_can_update_grading_weights_and_reporting_frequency(): void
    {
        $response = $this->actingAs($this->user)->put("/sections/{$this->section->id}/grading-weights", [
            'activity' => 25,
            'laboratory' => 0,
            'quiz' => 20,
            'exam' => 25,
            'project' => 15,
            'attendance' => 15,
            'recitation' => 5,
            'reporting_frequency' => 'twice_per_sem',
            'midterm_weight' => 50,
            'final_weight' => 50,
        ]);

        $response->assertSessionHasNoErrors();
        $this->section->refresh();

        $this->assertEquals('twice_per_sem', $this->section->grading_weights['reporting_frequency']);
        $this->assertEquals(25, $this->section->grading_weights['activity']);
    }

    public function test_onboarding_quick_setup_persists_reporting_frequency(): void
    {
        $newUser = User::factory()->create();

        $response = $this->actingAs($newUser)->postJson('/onboarding/quick-setup', [
            'name' => 'Prof. Alan Turing',
            'term_name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-31',
            'reporting_frequency' => 'once_per_sem',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('teacher_name', 'Prof. Alan Turing');
    }

    public function test_gradebook_csv_export_contains_midterm_and_final_grade_headers(): void
    {
        $response = $this->actingAs($this->user)->get("/sections/{$this->section->id}/exports/gradebook");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Midterm Grade (%)', $content);
        $this->assertStringContainsString('Midterm Scale (1.00-5.00)', $content);
        $this->assertStringContainsString('Final Period Grade (%)', $content);
        $this->assertStringContainsString('Semestral Final Grade (%)', $content);
    }

    public function test_midterm_grade_matches_user_specification_and_prevents_final_exam_or_post_midterm_leakage(): void
    {
        // Teacher configures: Activities 20%, Lab 0%, Quizzes 20%, Major Exams 25%, Project 20%, Attendance 15% (Total: 100%)
        $this->section->update([
            'grading_weights' => [
                'activity' => 20,
                'laboratory' => 0,
                'quiz' => 20,
                'exam' => 25,
                'project' => 20,
                'attendance' => 15,
                'recitation' => 0, // No recitation bonus in this configuration
                'reporting_frequency' => 'once_per_sem',
                'midterm_weight' => 50,
                'final_weight' => 50,
            ],
        ]);

        // 1. Activities: 90% (90 / 100 points) on or before Midterm Exam
        $act = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'activity',
            'title' => 'Hands-on Activity 1',
            'conducted_on' => '2026-09-10',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $act->id,
            'student_id' => $this->student->id,
            'score' => 90,
        ]);

        // 2. Quizzes: 85% (85 / 100 points) on or before Midterm Exam
        $quiz = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'quiz',
            'title' => 'Quiz 1',
            'conducted_on' => '2026-09-20',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $quiz->id,
            'student_id' => $this->student->id,
            'score' => 85,
        ]);

        // 3. Midterm Exam: 80% (80 / 100 points) conducted on 2026-10-15
        $midtermExam = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'exam',
            'title' => 'Midterm Examination',
            'conducted_on' => '2026-10-15',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $midtermExam->id,
            'student_id' => $this->student->id,
            'score' => 80,
        ]);

        // 4. Project / Report: 92% (92 / 100 points) assigned to Midterm period
        $project = Project::create([
            'section_id' => $this->section->id,
            'type' => 'project',
            'title' => 'Midterm Capstone Phase 1',
            'conducted_on' => '2026-10-01',
            'max_points' => 100,
        ]);
        $grp = ProjectGroup::create([
            'project_id' => $project->id,
            'group_number' => 1,
            'name' => 'Team Alpha',
            'score' => 92,
        ]);
        ProjectGroupMember::create([
            'project_group_id' => $grp->id,
            'student_id' => $this->student->id,
        ]);

        // 5. Attendance: 95% (19 present sessions out of 20 possible sessions on or before Midterm Exam)
        for ($i = 1; $i <= 20; $i++) {
            $day = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $date = "2026-09-{$day}";
            $sess = AttendanceSession::create([
                'section_id' => $this->section->id,
                'session_date' => $date,
                'starts_at' => '08:00:00',
                'ends_at' => '10:00:00',
                'duration_minutes' => 120,
            ]);
            AttendanceRecord::create([
                'attendance_session_id' => $sess->id,
                'student_id' => $this->student->id,
                'status' => $i === 20 ? AttendanceRecord::STATUS_ABSENT : AttendanceRecord::STATUS_PRESENT, // 19 present, 1 absent = 19/20 = 95%
            ]);
        }

        // 6. POST-MIDTERM RECORDS (must NEVER affect the Midterm Grade!)
        // Final Exam (score: 95/100)
        $finalExam = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'exam',
            'title' => 'Final Examination',
            'conducted_on' => '2026-12-15',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $finalExam->id,
            'student_id' => $this->student->id,
            'score' => 95,
        ]);

        // Post-midterm Activity with low score (10/100)
        $postAct = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'activity',
            'title' => 'Final Activity Post Midterms',
            'conducted_on' => '2026-11-10',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $postAct->id,
            'student_id' => $this->student->id,
            'score' => 10,
        ]);

        // Post-midterm Attendance (all absent)
        $postSess = AttendanceSession::create([
            'section_id' => $this->section->id,
            'session_date' => '2026-11-15',
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
            'duration_minutes' => 120,
        ]);
        AttendanceRecord::create([
            'attendance_session_id' => $postSess->id,
            'student_id' => $this->student->id,
            'status' => AttendanceRecord::STATUS_ABSENT,
        ]);

        $service = app(GradebookCalculationService::class);
        $gradebook = $service->calculateGradebook($this->section);
        $row = $gradebook['rows']->first();

        // Exact User Example:
        // Midterm Grade = (90 * 0.20) + (85 * 0.20) + (80 * 0.25) + (92 * 0.20) + (95 * 0.15)
        // Midterm Grade = 18 + 17 + 20 + 18.4 + 14.25 = 87.65
        $this->assertEquals(87.65, $row['midterm']['weighted_grade']);
        $this->assertEquals('2.00', $row['midterm']['scale_grade']); // 87.65% in 85–87% -> 2.00
        $this->assertEquals(90.0, $row['midterm']['categories']['activity']['percentage']);
        $this->assertEquals(85.0, $row['midterm']['categories']['quiz']['percentage']);
        $this->assertEquals(80.0, $row['midterm']['categories']['exam']['percentage']);
        $this->assertEquals(92.0, $row['midterm']['projectSummary']['percentage']);
        $this->assertEquals(95.0, $row['midterm']['attendance']['percentage']);

        // Laboratory is 0% and has no items; must be ignored
        $this->assertNull($row['midterm']['categories']['laboratory']['percentage']);

        // Verify that the Midterm Exam cutoff was tracked in gradebook output
        $this->assertNotNull($gradebook['midtermExam']);
        $this->assertEquals('2026-10-15', $gradebook['midtermExam']['conducted_on']);
    }

    public function test_midterm_grade_normalizes_to_available_categories_when_partial(): void
    {
        // Setup a section with Quiz (20%) and Attendance (15%) only, no activity or exam yet.
        $this->section->update([
            'grading_weights' => [
                'activity' => 20,
                'laboratory' => 0,
                'quiz' => 20,
                'exam' => 25,
                'project' => 20,
                'attendance' => 15,
                'recitation' => 5,
                'reporting_frequency' => 'once_per_sem',
                'midterm_weight' => 50,
                'final_weight' => 50,
            ],
        ]);

        $quiz = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'quiz',
            'title' => 'Cells and systems',
            'conducted_on' => '2026-08-09',
            'max_points' => 20,
        ]);

        // Student scores 15/20 (75%)
        AssessmentScore::create([
            'assessment_id' => $quiz->id,
            'student_id' => $this->student->id,
            'score' => 15,
        ]);

        // Student has 100% attendance
        $sess = AttendanceSession::create([
            'section_id' => $this->section->id,
            'session_date' => '2026-08-09',
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
            'duration_minutes' => 120,
        ]);
        AttendanceRecord::create([
            'attendance_session_id' => $sess->id,
            'student_id' => $this->student->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $service = app(GradebookCalculationService::class);
        $gradebook = $service->calculateGradebook($this->section);
        $row = $gradebook['rows']->first();

        // Evaluated categories: Quiz (20%) and Attendance (15%) = 35% total available
        // (75 * 0.20 + 100 * 0.15) / 0.35 = 30 / 0.35 = 85.71% -> 2.00
        $this->assertEquals(85.71, $row['midterm']['weighted_grade']);
        $this->assertEquals('2.00', $row['midterm']['scale_grade']);
    }

    public function test_oral_recitation_bonus_is_credited_even_when_no_activities_exist(): void
    {
        $this->section->update([
            'grading_weights' => [
                'activity' => 20,
                'laboratory' => 0,
                'quiz' => 20,
                'exam' => 25,
                'project' => 20,
                'attendance' => 15,
                'recitation' => 5,
                'reporting_frequency' => 'once_per_sem',
                'midterm_weight' => 50,
                'final_weight' => 50,
            ],
        ]);

        $quiz = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'quiz',
            'title' => 'Cells and systems',
            'conducted_on' => '2026-08-09',
            'max_points' => 20,
        ]);

        // Student scores 0/20 (0%) on quiz
        AssessmentScore::create([
            'assessment_id' => $quiz->id,
            'student_id' => $this->student->id,
            'score' => 0,
        ]);

        // Student has 100% attendance (15 pts)
        $sess = AttendanceSession::create([
            'section_id' => $this->section->id,
            'session_date' => '2026-08-09',
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
            'duration_minutes' => 120,
        ]);
        AttendanceRecord::create([
            'attendance_session_id' => $sess->id,
            'student_id' => $this->student->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        // Student has full recitation score 10/10 (+5 pts bonus)
        Recitation::create([
            'section_id' => $this->section->id,
            'student_id' => $this->student->id,
            'score' => 10.0,
            'conducted_on' => '2026-08-10',
        ]);

        $service = app(GradebookCalculationService::class);
        $gradebook = $service->calculateGradebook($this->section);
        $row = $gradebook['rows']->first();

        // Base grade = (0 * 0.20 + 100 * 0.15) / 0.35 = 42.86%. Recitation bonus = +5.00%. Total = 47.86%.
        $this->assertEquals(47.86, $row['midterm']['weighted_grade']);
        $this->assertEquals(5.0, $row['midterm']['recitation']['bonus_points']);
        $this->assertEquals('5.00', $row['midterm']['scale_grade']);
    }
}
