<?php

namespace Tests\Unit;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\GradebookCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradebookCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GradebookCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GradebookCalculationService();
    }

    public function test_it_calculates_gradebook_and_insights_with_accurate_metrics()
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester 2025-2026',
            'school_year' => '2025-2026',
            'starts_on' => Carbon::parse('2025-08-01'),
            'ends_on' => Carbon::parse('2025-12-15'),
            'is_current' => true,
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'name' => 'BSCS 3A',
            'subject_code' => 'CS301',
            'subject_title' => 'Algorithms',
        ]);

        $student1 = Student::create([
            'section_id' => $section->id,
            'student_number' => '2023-0001',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'is_active' => true,
        ]);

        $student2 = Student::create([
            'section_id' => $section->id,
            'student_number' => '2023-0002',
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'is_active' => true,
        ]);

        $assessment = Assessment::create([
            'section_id' => $section->id,
            'type' => 'activity',
            'assessment_number' => 1,
            'title' => 'Lab Exercise 1',
            'max_points' => 100,
            'conducted_on' => Carbon::today(),
        ]);

        AssessmentScore::create([
            'assessment_id' => $assessment->id,
            'student_id' => $student1->id,
            'score' => 90.0,
        ]);

        AssessmentScore::create([
            'assessment_id' => $assessment->id,
            'student_id' => $student2->id,
            'score' => 60.0,
        ]);

        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => Carbon::today(),
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
            'duration_minutes' => 120,
        ]);

        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student1->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student2->id,
            'status' => AttendanceRecord::STATUS_ABSENT,
        ]);

        $gradebook = $this->service->calculateGradebook($section);
        $this->assertCount(2, $gradebook['rows']);

        $insights = $this->service->getSectionInsights($section);
        $this->assertEquals('BSCS 3A', $insights['section_name']);
        $this->assertEquals(2, $insights['total_students']);
        $this->assertEquals(1, $insights['passing_count']);
        $this->assertGreaterThanOrEqual(1, count($insights['at_risk_students']));
    }
}
