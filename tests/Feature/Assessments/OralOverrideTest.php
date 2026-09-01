<?php

namespace Tests\Feature\Assessments;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OralOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function createSectionWithTeacher(): array
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => 'First semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS101',
            'subject_title' => 'Intro to CS',
            'name' => 'Section 1',
            'grading_weights' => [
                'activity' => 20,
                'quiz' => 20,
                'exam' => 25,
                'project' => 20,
                'attendance' => 15,
                'recitation' => 5,
            ],
        ]);

        return [$user, $section];
    }

    private function createSessionWithAttendance(Section $section, Student $student, string $date, string $status): AttendanceSession
    {
        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => $date,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'duration_minutes' => 60,
        ]);

        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => $status,
        ]);

        return $session;
    }

    public function test_teacher_can_override_oral_points_allocated_equally_across_present_days(): void
    {
        [$teacher, $section] = $this->createSectionWithTeacher();

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-001',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'is_active' => true,
        ]);

        // Student present on 4 dates, absent on 1 date
        $this->createSessionWithAttendance($section, $student, '2026-08-10', AttendanceRecord::STATUS_PRESENT);
        $this->createSessionWithAttendance($section, $student, '2026-08-12', AttendanceRecord::STATUS_PRESENT);
        $this->createSessionWithAttendance($section, $student, '2026-08-14', AttendanceRecord::STATUS_PRESENT);
        $this->createSessionWithAttendance($section, $student, '2026-08-17', AttendanceRecord::STATUS_PRESENT);
        $this->createSessionWithAttendance($section, $student, '2026-08-19', AttendanceRecord::STATUS_ABSENT);

        // Teacher overrides with 40 points (4 days * 10 max = 40 max points)
        $response = $this->actingAs($teacher)->post(route('sections.reports.gradebook.override-oral', $section), [
            'student_id' => $student->id,
            'points' => 40,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Check that 4 recitations were created on the present days
        $recitations = Recitation::where('section_id', $section->id)
            ->where('student_id', $student->id)
            ->get();

        $this->assertCount(4, $recitations);
        foreach ($recitations as $rec) {
            $this->assertEquals(10.00, (float) $rec->score);
            $this->assertNotEquals('2026-08-19', $rec->conducted_on->toDateString());
        }
        $this->assertEquals(40.00, (float) $recitations->sum('score'));
    }

    public function test_override_allocates_equal_value_below_max(): void
    {
        [$teacher, $section] = $this->createSectionWithTeacher();

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-002',
            'first_name' => 'Alan',
            'last_name' => 'Turing',
            'is_active' => true,
        ]);

        // Present on 5 days
        for ($i = 1; $i <= 5; $i++) {
            $this->createSessionWithAttendance($section, $student, "2026-08-0{$i}", AttendanceRecord::STATUS_PRESENT);
        }

        // 35 points across 5 days = exactly 7.00 points per day
        $response = $this->actingAs($teacher)->post(route('sections.reports.gradebook.override-oral', $section), [
            'student_id' => $student->id,
            'points' => 35,
        ]);

        $response->assertSessionHasNoErrors();

        $recitations = Recitation::where('student_id', $student->id)->get();
        $this->assertCount(5, $recitations);
        foreach ($recitations as $rec) {
            $this->assertEquals(7.00, (float) $rec->score);
        }
    }

    public function test_points_cannot_surpass_max_point_for_oral(): void
    {
        [$teacher, $section] = $this->createSectionWithTeacher();

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-003',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'is_active' => true,
        ]);

        // Present on 3 days => Max possible is 30.00 points
        $this->createSessionWithAttendance($section, $student, '2026-08-10', AttendanceRecord::STATUS_PRESENT);
        $this->createSessionWithAttendance($section, $student, '2026-08-12', AttendanceRecord::STATUS_PRESENT);
        $this->createSessionWithAttendance($section, $student, '2026-08-14', AttendanceRecord::STATUS_PRESENT);

        // Attempt to allocate 50 points (surpasses max of 30)
        $response = $this->actingAs($teacher)->post(route('sections.reports.gradebook.override-oral', $section), [
            'student_id' => $student->id,
            'points' => 50,
        ]);

        $response->assertSessionHasErrors('points');
        $this->assertDatabaseEmpty('recitations');
    }

    public function test_student_with_zero_present_days_cannot_receive_override(): void
    {
        [$teacher, $section] = $this->createSectionWithTeacher();

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-004',
            'first_name' => 'Linus',
            'last_name' => 'Torvalds',
            'is_active' => true,
        ]);

        // Student only has an absent session
        $this->createSessionWithAttendance($section, $student, '2026-08-10', AttendanceRecord::STATUS_ABSENT);

        $response = $this->actingAs($teacher)->post(route('sections.reports.gradebook.override-oral', $section), [
            'student_id' => $student->id,
            'points' => 10,
        ]);

        $response->assertSessionHasErrors('points');
        $this->assertDatabaseEmpty('recitations');
    }

    public function test_override_can_optionally_include_late_attendance_days(): void
    {
        [$teacher, $section] = $this->createSectionWithTeacher();

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-005',
            'first_name' => 'Margaret',
            'last_name' => 'Hamilton',
            'is_active' => true,
        ]);

        // 2 present, 2 late = 4 eligible days if late included
        $this->createSessionWithAttendance($section, $student, '2026-08-10', AttendanceRecord::STATUS_PRESENT);
        $this->createSessionWithAttendance($section, $student, '2026-08-11', AttendanceRecord::STATUS_PRESENT);
        $this->createSessionWithAttendance($section, $student, '2026-08-12', AttendanceRecord::STATUS_LATE);
        $this->createSessionWithAttendance($section, $student, '2026-08-13', AttendanceRecord::STATUS_LATE);

        // Without include_late, 40 points surpasses max (2 * 10 = 20)
        $resp1 = $this->actingAs($teacher)->post(route('sections.reports.gradebook.override-oral', $section), [
            'student_id' => $student->id,
            'points' => 40,
            'include_late' => false,
        ]);
        $resp1->assertSessionHasErrors('points');

        // With include_late = true, 4 days * 10 = 40 max points allowed
        $resp2 = $this->actingAs($teacher)->post(route('sections.reports.gradebook.override-oral', $section), [
            'student_id' => $student->id,
            'points' => 40,
            'include_late' => true,
        ]);
        $resp2->assertSessionHasNoErrors();

        $this->assertCount(4, Recitation::where('student_id', $student->id)->get());
    }

    public function test_section_wide_override_caps_each_student_at_their_respective_max(): void
    {
        [$teacher, $section] = $this->createSectionWithTeacher();

        $studentA = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-A',
            'first_name' => 'Student',
            'last_name' => 'Alpha',
            'is_active' => true,
        ]);
        $studentB = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-B',
            'first_name' => 'Student',
            'last_name' => 'Beta',
            'is_active' => true,
        ]);

        // Student A has 5 present days (max 50), Student B has 3 present days (max 30)
        for ($i = 1; $i <= 5; $i++) {
            $session = AttendanceSession::create([
                'section_id' => $section->id,
                'session_date' => "2026-08-0{$i}",
                'starts_at' => '08:00',
                'ends_at' => '09:00',
                'duration_minutes' => 60,
            ]);

            AttendanceRecord::create([
                'attendance_session_id' => $session->id,
                'student_id' => $studentA->id,
                'status' => AttendanceRecord::STATUS_PRESENT,
            ]);

            if ($i <= 3) {
                AttendanceRecord::create([
                    'attendance_session_id' => $session->id,
                    'student_id' => $studentB->id,
                    'status' => AttendanceRecord::STATUS_PRESENT,
                ]);
            }
        }

        // Section-wide override with 50 points target
        $response = $this->actingAs($teacher)->post(route('sections.reports.gradebook.override-oral', $section), [
            'apply_to_all' => true,
            'points' => 50,
        ]);

        $response->assertSessionHasNoErrors();

        // Student A gets 50 points (5 * 10)
        $this->assertEquals(50.00, (float) Recitation::where('student_id', $studentA->id)->sum('score'));

        // Student B gets capped at 30 points (3 * 10)
        $this->assertEquals(30.00, (float) Recitation::where('student_id', $studentB->id)->sum('score'));
    }

    public function test_unauthorized_user_cannot_override_oral_points(): void
    {
        [$teacher, $section] = $this->createSectionWithTeacher();
        $otherUser = User::factory()->create();

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-006',
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_active' => true,
        ]);

        $response = $this->actingAs($otherUser)->post(route('sections.reports.gradebook.override-oral', $section), [
            'student_id' => $student->id,
            'points' => 10,
        ]);

        $response->assertForbidden();
    }

    public function test_gradebook_renders_and_computes_overridden_oral_points(): void
    {
        [$teacher, $section] = $this->createSectionWithTeacher();

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-007',
            'first_name' => 'Barbara',
            'last_name' => 'Liskov',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $this->createSessionWithAttendance($section, $student, "2026-08-0{$i}", AttendanceRecord::STATUS_PRESENT);
        }

        // Override with 40 points (10/day)
        $this->actingAs($teacher)->post(route('sections.reports.gradebook.override-oral', $section), [
            'student_id' => $student->id,
            'points' => 40,
        ]);

        $response = $this->actingAs($teacher)->get(route('sections.reports.gradebook', $section));
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('reports/Gradebook')
            ->has('rows', 1)
            ->where('rows.0.recitation.count', 4)
            ->where('rows.0.recitation.total_score', fn ($score) => (float) $score === 40.0)
            ->where('rows.0.recitation.avg_score', fn ($avg) => (float) $avg === 10.0)
            ->where('rows.0.recitation.bonus_points', fn ($bonus) => (float) $bonus === 5.0) // 10/10 * 5 bonus cap = 5.0
            ->where('rows.0.attendance.present_count', 4)
            ->has('rows.0.attendance.present_dates', 4)
        );
    }
}
