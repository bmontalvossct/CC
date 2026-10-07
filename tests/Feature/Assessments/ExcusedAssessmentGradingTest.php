<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\GradebookCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExcusedAssessmentGradingTest extends TestCase
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
            'name' => 'BSCS 4A',
            'subject_code' => 'CS 401',
            'subject_title' => 'Software Engineering',
            'grading_weights' => [
                'activity' => 0,
                'laboratory' => 0,
                'quiz' => 100, // 100% Quiz for direct isolated calculation testing
                'exam' => 0,
                'project' => 0,
                'attendance' => 0,
                'recitation' => 0,
            ],
        ]);

        $this->student = Student::create([
            'section_id' => $this->section->id,
            'student_number' => '2026-1234',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'is_active' => true,
        ]);
    }

    public function test_excused_assessment_excludes_max_points_from_student_gradebook_calculation(): void
    {
        // Example from prompt:
        // Quiz 1 = 18/20
        // Quiz 2 = EXCUSED / 30
        // Quiz 3 = 40/50
        // Denominator must be 20 + 50 = 70 (not 100).
        // Percentage = 58 / 70 * 100 = 82.86%
        $q1 = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'quiz',
            'title' => 'Quiz 1',
            'conducted_on' => '2026-09-01',
            'max_points' => 20,
        ]);
        AssessmentScore::create([
            'assessment_id' => $q1->id,
            'student_id' => $this->student->id,
            'score' => 18,
        ]);

        $q2 = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'quiz',
            'title' => 'Quiz 2',
            'conducted_on' => '2026-09-10',
            'max_points' => 30,
        ]);
        AssessmentScore::create([
            'assessment_id' => $q2->id,
            'student_id' => $this->student->id,
            'score' => null,
            'remarks' => 'EXCUSED: Medical reason',
        ]);

        $q3 = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'quiz',
            'title' => 'Quiz 3',
            'conducted_on' => '2026-09-20',
            'max_points' => 50,
        ]);
        AssessmentScore::create([
            'assessment_id' => $q3->id,
            'student_id' => $this->student->id,
            'score' => 40,
        ]);

        $service = app(GradebookCalculationService::class);
        $gradebook = $service->calculateGradebook($this->section, true);
        $row = $gradebook['rows']->first();

        // Check quiz category
        $quizCat = $row['categories']['quiz'];
        $this->assertEquals(58.0, $quizCat['earned']);
        $this->assertEquals(70.0, $quizCat['possible']);
        $this->assertEquals(82.86, $quizCat['percentage']);

        // Check overall weighted grade and remarks
        $this->assertEquals(82.86, $row['weighted_grade']);
        $this->assertEquals('1.7', $row['scale_grade']);
        $this->assertEquals('Passed', $row['grade_remarks']);
    }

    public function test_custom_transmutation_table_on_section_is_applied(): void
    {
        // Custom section grading scale where 80-100 is 1.00 PASSED, < 80 is 5.00 FAILED
        $this->section->update([
            'grading_weights' => array_merge($this->section->grading_weights, [
                'transmutation_table' => [
                    ['minimum_percentage' => 80.0, 'maximum_percentage' => 100.0, 'grade_equivalent' => '1.00', 'remarks' => 'HONOR_PASS'],
                    ['minimum_percentage' => 0.0,  'maximum_percentage' => 79.99, 'grade_equivalent' => '5.00', 'remarks' => 'RETAKE'],
                ],
            ]),
        ]);

        $q1 = Assessment::create([
            'section_id' => $this->section->id,
            'type' => 'quiz',
            'title' => 'Quiz 1',
            'conducted_on' => '2026-09-01',
            'max_points' => 100,
        ]);
        AssessmentScore::create([
            'assessment_id' => $q1->id,
            'student_id' => $this->student->id,
            'score' => 85,
        ]);

        $service = app(GradebookCalculationService::class);
        $gradebook = $service->calculateGradebook($this->section, true);
        $row = $gradebook['rows']->first();

        $this->assertEquals(85.0, $row['weighted_grade']);
        $this->assertEquals('1.00', $row['scale_grade']);
        $this->assertEquals('HONOR_PASS', $row['grade_remarks']);
    }
}
