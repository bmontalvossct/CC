<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function section(User $user): Section
    {
        $term = AcademicTerm::create([
            'user_id' => $user->id, 'name' => 'First semester', 'school_year' => '2026-2027',
            'starts_on' => '2026-08-01', 'ends_on' => '2026-12-20',
        ]);

        return Section::create([
            'user_id' => $user->id, 'academic_term_id' => $term->id,
            'subject_code' => 'MATH101', 'subject_title' => 'Mathematics', 'name' => 'Section A',
        ]);
    }

    public function test_teacher_can_create_an_assessment_and_matching_session_is_selected(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $session = AttendanceSession::create([
            'section_id' => $section->id, 'session_date' => '2026-08-10',
            'starts_at' => '08:00', 'ends_at' => '09:00', 'duration_minutes' => 60,
        ]);

        $response = $this->actingAs($user)->post(route('sections.assessments.store', $section), [
            'type' => 'quiz', 'title' => 'Fractions quiz',
            'conducted_on' => '2026-08-10', 'max_points' => 20,
        ]);

        $assessment = Assessment::firstOrFail();
        $response->assertRedirect(route('sections.assessments.show', [$section, $assessment]));
        $this->assertSame($session->id, $assessment->attendance_session_id);
        $this->assertSame('quiz', $assessment->type);
    }

    public function test_zero_is_recorded_while_blank_remains_missing(): void
    {
        [$user, $section, $student, $assessment] = $this->scoreFixture();

        $this->actingAs($user)->patchJson(route('sections.assessments.scores.update', [$section, $assessment, $student]), ['score' => 0])
            ->assertOk()->assertJsonPath('score', '0.00');
        $this->assertSame('0.00', $assessment->scores()->firstOrFail()->score);

        $this->actingAs($user)->patchJson(route('sections.assessments.scores.update', [$section, $assessment, $student]), ['score' => null])
            ->assertOk()->assertJsonPath('score', null);
        $this->assertNull($assessment->scores()->firstOrFail()->score);
    }

    public function test_absent_students_require_an_explicit_override(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $student = Student::create([
            'section_id' => $section->id, 'student_number' => '2026-002',
            'first_name' => 'Grace', 'last_name' => 'Hopper',
        ]);
        $session = AttendanceSession::create([
            'section_id' => $section->id, 'session_date' => '2026-08-10',
            'starts_at' => '09:00', 'ends_at' => '10:00', 'duration_minutes' => 60,
        ]);
        AttendanceRecord::create([
            'attendance_session_id' => $session->id, 'student_id' => $student->id,
            'status' => 'absent', 'attended_minutes' => 0,
        ]);
        $assessment = Assessment::create([
            'section_id' => $section->id, 'attendance_session_id' => $session->id,
            'type' => 'exam', 'title' => 'Midterm', 'conducted_on' => '2026-08-10', 'max_points' => 100,
        ]);

        $url = route('sections.assessments.scores.update', [$section, $assessment, $student]);
        $this->actingAs($user)->patchJson($url, ['score' => 75])->assertUnprocessable();
        $this->actingAs($user)->patchJson($url, ['score' => 75, 'include_absent' => true])
            ->assertOk()->assertJsonPath('absence_override', true);
    }

    public function test_teachers_cannot_access_another_teachers_assessments(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $section = $this->section($owner);
        $assessment = Assessment::create([
            'section_id' => $section->id, 'type' => 'quiz', 'title' => 'Private quiz',
            'conducted_on' => '2026-08-10', 'max_points' => 10,
        ]);

        $this->actingAs($intruder)->get(route('sections.assessments.show', [$section, $assessment]))->assertForbidden();
        $this->actingAs($intruder)->get(route('sections.exports.gradebook', $section))->assertForbidden();
    }

    public function test_score_cannot_exceed_the_assessment_maximum(): void
    {
        [$user, $section, $student, $assessment] = $this->scoreFixture();

        $this->actingAs($user)
            ->patchJson(route('sections.assessments.scores.update', [$section, $assessment, $student]), ['score' => 10.01])
            ->assertUnprocessable()->assertJsonValidationErrors('score');
    }

    private function scoreFixture(): array
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $student = Student::create([
            'section_id' => $section->id, 'student_number' => '2026-001',
            'first_name' => 'Ada', 'last_name' => 'Lovelace',
        ]);
        $assessment = Assessment::create([
            'section_id' => $section->id, 'type' => 'activity', 'title' => 'Exercise',
            'conducted_on' => '2026-08-10', 'max_points' => 10,
        ]);

        return [$user, $section, $student, $assessment];
    }
}
