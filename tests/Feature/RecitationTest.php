<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Recitation;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecitationTest extends TestCase
{
    use RefreshDatabase;

    private function createSection(User $user): Section
    {
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => 'First Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
        ]);

        return Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS101',
            'subject_title' => 'Computer Science',
            'name' => 'Section A',
        ]);
    }

    public function test_teacher_can_view_oral_participation_page(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('sections.recitation.index', $section));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('sections/OralParticipation')
            ->has('students', 1)
            ->where('students.0.student_number', 'STU-001')
        );
    }

    public function test_teacher_can_record_oral_participation_score(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(
            route('sections.recitation.score', [$section, $student]),
            [
                'accuracy' => 5,
                'delivery' => 4,
                'comments' => 'Clear explanation and confident delivery.',
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('recitations', [
            'section_id' => $section->id,
            'student_id' => $student->id,
            'accuracy' => 5,
            'delivery' => 4,
            'score' => 9.00,
            'comments' => 'Clear explanation and confident delivery.',
        ]);
    }

    public function test_teacher_can_update_existing_oral_score_for_same_day(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-002',
            'first_name' => 'Pedro',
            'last_name' => 'Penduko',
            'is_active' => true,
        ]);

        // First score
        $this->actingAs($user)->post(
            route('sections.recitation.score', [$section, $student]),
            [
                'accuracy' => 3,
                'delivery' => 3,
                'comments' => 'First attempt',
            ]
        );

        $this->assertDatabaseHas('recitations', [
            'student_id' => $student->id,
            'score' => 6.00,
        ]);

        // Update score
        $response = $this->actingAs($user)->post(
            route('sections.recitation.score', [$section, $student]),
            [
                'accuracy' => 5,
                'delivery' => 5,
                'comments' => 'Second attempt - excellent improvement',
            ]
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, Recitation::where('student_id', $student->id)->count());
        $this->assertDatabaseHas('recitations', [
            'student_id' => $student->id,
            'accuracy' => 5,
            'delivery' => 5,
            'score' => 10.00,
            'comments' => 'Second attempt - excellent improvement',
        ]);
    }

    public function test_teacher_cannot_record_oral_score_for_another_teachers_section(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $section = $this->createSection($owner);
        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-003',
            'first_name' => 'Jose',
            'last_name' => 'Rizal',
            'is_active' => true,
        ]);

        $response = $this->actingAs($otherUser)->post(
            route('sections.recitation.score', [$section, $student]),
            [
                'accuracy' => 4,
                'delivery' => 4,
            ]
        );

        $response->assertForbidden();
    }

    public function test_teacher_can_record_direct_score_from_0_to_10(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-004',
            'first_name' => 'Andres',
            'last_name' => 'Bonifacio',
            'is_active' => true,
        ]);

        // Score 0
        $response = $this->actingAs($user)->post(
            route('sections.recitation.score', [$section, $student]),
            [
                'score' => 0,
                'accuracy' => 0,
                'delivery' => 0,
                'comments' => 'No answer provided.',
            ]
        );
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recitations', [
            'student_id' => $student->id,
            'score' => 0.00,
        ]);

        // Adjust to 8.5
        $response = $this->actingAs($user)->post(
            route('sections.recitation.score', [$section, $student]),
            [
                'score' => 8.5,
                'comments' => 'Follow up answer given.',
            ]
        );
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recitations', [
            'student_id' => $student->id,
            'score' => 8.50,
        ]);
    }

    public function test_teacher_can_view_student_recitation_logs_and_adjust_log(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-005',
            'first_name' => 'Apolinario',
            'last_name' => 'Mabini',
            'is_active' => true,
        ]);

        $rec1 = Recitation::create([
            'section_id' => $section->id,
            'student_id' => $student->id,
            'conducted_on' => '2026-08-10',
            'accuracy' => 4,
            'delivery' => 4,
            'score' => 8,
            'comments' => 'First session',
        ]);

        $rec2 = Recitation::create([
            'section_id' => $section->id,
            'student_id' => $student->id,
            'conducted_on' => '2026-08-12',
            'accuracy' => 5,
            'delivery' => 5,
            'score' => 10,
            'comments' => 'Second session',
        ]);

        $response = $this->actingAs($user)->get(route('sections.recitation.index', $section));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('sections/OralParticipation')
            ->has('students.0.recitations', 2)
            ->where('students.0.times_called', 2)
            ->where('students.0.avg_score', 9)
        );

        // Teacher adjusts first log entry from 8 to 9.5
        $updateResponse = $this->actingAs($user)->put(
            route('sections.recitations.update', [$section, $rec1]),
            [
                'score' => 9.5,
                'accuracy' => 5,
                'delivery' => 5,
                'conducted_on' => '2026-08-10',
                'comments' => 'Adjusted after review',
            ]
        );
        $updateResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recitations', [
            'id' => $rec1->id,
            'score' => 9.50,
            'comments' => 'Adjusted after review',
        ]);

        // Teacher deletes second log entry
        $deleteResponse = $this->actingAs($user)->delete(
            route('sections.recitations.destroy', [$section, $rec2])
        );
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('recitations', [
            'id' => $rec2->id,
        ]);
    }

    public function test_absent_student_cannot_receive_recitation_score_for_that_date(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-ABS-01',
            'first_name' => 'Jose',
            'last_name' => 'Rizal',
            'is_active' => true,
        ]);

        $sessionDate = now()->toDateString();
        $session = \App\Models\AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => $sessionDate,
            'starts_at' => '08:00:00',
            'ends_at' => '09:30:00',
            'duration_minutes' => 90,
        ]);

        \App\Models\AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => \App\Models\AttendanceRecord::STATUS_ABSENT,
        ]);

        // Attempt to score absent student
        $response = $this->actingAs($user)->post(
            route('sections.recitation.score', [$section, $student]),
            [
                'score' => 9,
                'accuracy' => 5,
                'delivery' => 4,
                'conducted_on' => $sessionDate,
            ]
        );

        $response->assertSessionHasErrors(['score']);
        $this->assertDatabaseMissing('recitations', [
            'section_id' => $section->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_present_and_late_students_can_receive_recitation_scores(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $presentStudent = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-PRES-01',
            'first_name' => 'Emilio',
            'last_name' => 'Aguinaldo',
            'is_active' => true,
        ]);
        $lateStudent = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-LATE-01',
            'first_name' => 'Melchora',
            'last_name' => 'Aquino',
            'is_active' => true,
        ]);

        $sessionDate = now()->toDateString();
        $session = \App\Models\AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => $sessionDate,
            'starts_at' => '08:00:00',
            'ends_at' => '09:30:00',
            'duration_minutes' => 90,
        ]);

        \App\Models\AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $presentStudent->id,
            'status' => \App\Models\AttendanceRecord::STATUS_PRESENT,
        ]);

        \App\Models\AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $lateStudent->id,
            'status' => \App\Models\AttendanceRecord::STATUS_LATE,
        ]);

        // Score present student
        $presResponse = $this->actingAs($user)->post(
            route('sections.recitation.score', [$section, $presentStudent]),
            [
                'score' => 8,
                'accuracy' => 4,
                'delivery' => 4,
                'conducted_on' => $sessionDate,
            ]
        );
        $presResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recitations', [
            'student_id' => $presentStudent->id,
            'score' => 8.00,
        ]);

        // Score late student
        $lateResponse = $this->actingAs($user)->post(
            route('sections.recitation.score', [$section, $lateStudent]),
            [
                'score' => 7,
                'accuracy' => 4,
                'delivery' => 3,
                'conducted_on' => $sessionDate,
            ]
        );
        $lateResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recitations', [
            'student_id' => $lateStudent->id,
            'score' => 7.00,
        ]);
    }

    public function test_oral_participation_page_includes_attendance_status_for_today(): void
    {
        $user = User::factory()->create();
        $section = $this->createSection($user);
        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => 'STU-STAT-01',
            'first_name' => 'Gabriela',
            'last_name' => 'Silang',
            'is_active' => true,
        ]);

        $sessionDate = now()->toDateString();
        $session = \App\Models\AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => $sessionDate,
            'starts_at' => '08:00:00',
            'ends_at' => '09:30:00',
            'duration_minutes' => 90,
        ]);

        \App\Models\AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => \App\Models\AttendanceRecord::STATUS_ABSENT,
        ]);

        $response = $this->actingAs($user)->get(route('sections.recitation.index', $section));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('sections/OralParticipation')
            ->has('students', 1)
            ->where('students.0.attendance_status', 'absent')
            ->where('students.0.is_absent', true)
            ->where('students.0.can_recite', false)
            ->where('hasTodayAttendance', true)
        );
    }
}
