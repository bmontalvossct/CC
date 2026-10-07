<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Autochecker\ChatToolRegistry;
use App\Services\GradebookCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionPassingRatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_section_with_custom_passing_rates(): void
    {
        $user = User::factory()->create();

        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
        ]);

        $payload = [
            'subject_code' => 'CS 101',
            'subject_title' => 'Introduction to Computer Science',
            'name' => 'CS 1-A',
            'room' => 'Lab 1',
            'term' => [
                'name' => '1st Semester',
                'school_year' => '2026-2027',
                'starts_on' => '2026-08-01',
                'ends_on' => '2026-12-15',
            ],
            'schedules' => [
                [
                    'day_of_week' => 1,
                    'starts_at' => '08:00',
                    'ends_at' => '09:30',
                    'room' => 'Lab 1',
                    'schedule_type' => 'lecture',
                ],
            ],
            'passing_rates' => [
                'quiz' => 60,
                'activity' => 70,
                'project' => 80,
                'exam' => 75,
            ],
        ];

        $response = $this->actingAs($user)->post(route('sections.store'), $payload);

        $response->assertRedirect();

        $section = Section::where('name', 'CS 1-A')->firstOrFail();
        $this->assertNotNull($section->grading_weights);
        $this->assertEquals(60, $section->grading_weights['passing_rates']['quiz']);
        $this->assertEquals(70, $section->grading_weights['passing_rates']['activity']);
        $this->assertEquals(80, $section->grading_weights['passing_rates']['project']);
        $this->assertEquals(75, $section->grading_weights['passing_rates']['exam']);
    }

    public function test_teacher_can_update_section_passing_rates(): void
    {
        $user = User::factory()->create();

        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS 101',
            'subject_title' => 'Introduction to Computer Science',
            'name' => 'CS 1-A',
            'room' => 'Lab 1',
            'grading_weights' => [
                'passing_rates' => [
                    'quiz' => 75,
                    'activity' => 75,
                    'project' => 75,
                    'exam' => 75,
                ],
            ],
        ]);

        $updatePayload = [
            'subject_code' => 'CS 101',
            'subject_title' => 'Introduction to Computer Science',
            'name' => 'CS 1-A Updated',
            'room' => 'Lab 2',
            'term' => [
                'name' => '1st Semester',
                'school_year' => '2026-2027',
                'starts_on' => '2026-08-01',
                'ends_on' => '2026-12-15',
            ],
            'schedules' => [
                [
                    'day_of_week' => 2,
                    'starts_at' => '10:00',
                    'ends_at' => '11:30',
                    'room' => 'Lab 2',
                    'schedule_type' => 'lab',
                ],
            ],
            'passing_rates' => [
                'quiz' => 65,
                'activity' => 65,
                'project' => 85,
                'exam' => 70,
            ],
        ];

        $response = $this->actingAs($user)->put(route('sections.update', $section), $updatePayload);

        $response->assertRedirect();

        $section->refresh();
        $this->assertEquals('CS 1-A Updated', $section->name);
        $this->assertEquals(65, $section->grading_weights['passing_rates']['quiz']);
        $this->assertEquals(65, $section->grading_weights['passing_rates']['activity']);
        $this->assertEquals(85, $section->grading_weights['passing_rates']['project']);
        $this->assertEquals(70, $section->grading_weights['passing_rates']['exam']);
    }

    public function test_gradebook_and_assessment_analytics_respect_custom_passing_rates(): void
    {
        $user = User::factory()->create();

        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
        ]);

        // Section with 60% quiz passing rate
        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS 101',
            'subject_title' => 'Introduction to Computer Science',
            'name' => 'CS 1-A',
            'room' => 'Lab 1',
            'grading_weights' => [
                'passing_rates' => [
                    'quiz' => 60,
                    'activity' => 70,
                    'project' => 80,
                    'exam' => 75,
                ],
            ],
        ]);

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => '2026-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        // Quiz with max 100 points
        $assessment = Assessment::create([
            'section_id' => $section->id,
            'title' => 'Quiz 1',
            'type' => 'quiz',
            'max_points' => 100,
            'conducted_on' => '2026-08-10',
        ]);

        // Student scores 65/100 (65%).
        // Under default 75%, this would be failing.
        // Under section custom 60%, this is PASSING!
        AssessmentScore::create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 65,
        ]);

        // 1. Check GradebookCalculationService returns passing rates
        $gradebookService = app(GradebookCalculationService::class);
        $gradebook = $gradebookService->calculateGradebook($section);
        $this->assertEquals(60, $gradebook['gradingWeights']['passing_rates']['quiz']);

        // 2. Check ChatToolRegistry assessment analytics uses 60% threshold
        $toolRegistry = app(ChatToolRegistry::class);
        $analytics = $toolRegistry->executeTool('get_assessment_analytics', [
            'section_id' => $section->id,
            'assessment_id' => $assessment->id,
        ], $user);

        $assessmentData = $analytics['result']['assessments'][0];
        // Since score is 65% and passing rate is 60%, pass rate should be 100% (1 of 1 passed)
        $this->assertEquals(100.0, $assessmentData['passing_rate_pct']);
    }

    public function test_default_passing_rate_is_fifty_percent(): void
    {
        $this->assertEquals(50, GradebookCalculationService::DEFAULT_PASSING_RATES['quiz']);
        $this->assertEquals(50, GradebookCalculationService::DEFAULT_PASSING_RATES['activity']);
        $this->assertEquals(50, GradebookCalculationService::DEFAULT_PASSING_RATES['project']);
        $this->assertEquals(50, GradebookCalculationService::DEFAULT_PASSING_RATES['exam']);

        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS 101',
            'subject_title' => 'Intro',
            'name' => 'CS 1-Default',
            'room' => 'Lab 1',
        ]);

        $gradebookService = app(GradebookCalculationService::class);
        $gradebook = $gradebookService->calculateGradebook($section);

        $this->assertEquals(50, $gradebook['gradingWeights']['passing_rates']['quiz']);
        $this->assertEquals(50, $gradebook['gradingWeights']['passing_rates']['activity']);
        $this->assertEquals(50, $gradebook['gradingWeights']['passing_rates']['project']);
        $this->assertEquals(50, $gradebook['gradingWeights']['passing_rates']['exam']);
    }
}
