<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSection(User $teacher, int $studentCount = 2): Section
    {
        $term = AcademicTerm::create(['user_id' => $teacher->id, 'name' => 'First Semester', 'school_year' => '2026-2027', 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-18']);
        $section = Section::create(['user_id' => $teacher->id, 'academic_term_id' => $term->id, 'subject_code' => 'MATH101', 'subject_title' => 'College Algebra', 'name' => 'Section A', 'enrollment_open' => true]);
        foreach (range(1, $studentCount) as $number) {
            Student::create(['section_id' => $section->id, 'student_number' => "2026-00{$number}", 'first_name' => "Student {$number}", 'last_name' => 'Tester']);
        }

        return $section;
    }

    public function test_teacher_starts_session_with_every_current_student_present(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection($teacher, 3);
        $response = $this->actingAs($teacher)->post(route('attendance.sections.store', $section), ['session_date' => '2026-08-10', 'starts_at' => '08:00', 'ends_at' => '09:30', 'notes' => 'Morning class']);
        $session = AttendanceSession::sole();
        $response->assertRedirect(route('attendance.sessions.show', $session));
        $this->assertSame(90, $session->duration_minutes);
        $this->assertCount(3, $session->records);
        $this->assertSame(['present'], $session->records->pluck('status')->unique()->values()->all());
        $this->assertSame([90], $session->records->pluck('attended_minutes')->unique()->values()->all());
    }

    public function test_exact_section_date_and_start_time_must_be_unique(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection($teacher);
        $payload = ['session_date' => '2026-08-10', 'starts_at' => '08:00', 'ends_at' => '09:00'];
        $this->actingAs($teacher)->post(route('attendance.sections.store', $section), $payload)->assertSessionHasNoErrors();
        $this->actingAs($teacher)->from(route('attendance.sections.index', $section))->post(route('attendance.sections.store', $section), $payload)->assertSessionHasErrors('starts_at');
        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_another_teacher_cannot_access_section_attendance(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $section = $this->makeSection($owner);
        $this->actingAs($outsider)->get(route('attendance.sections.index', $section))->assertForbidden();
        $this->actingAs($outsider)->post(route('attendance.sections.store', $section), ['session_date' => '2026-08-10', 'starts_at' => '08:00', 'ends_at' => '09:00'])->assertForbidden();
    }

    public function test_teacher_can_mark_absent_and_restore_present(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection($teacher, 1);
        $session = AttendanceSession::create(['section_id' => $section->id, 'session_date' => '2026-08-10', 'starts_at' => '08:00', 'ends_at' => '09:30', 'duration_minutes' => 90]);
        $record = AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $section->students()->first()->id, 'status' => 'present', 'attended_minutes' => 90]);
        $this->actingAs($teacher)->patchJson(route('attendance.records.update', $record), ['status' => 'absent'])->assertOk()->assertJsonPath('record.status', 'absent')->assertJsonPath('record.attended_minutes', 0);
        $this->actingAs($teacher)->patchJson(route('attendance.records.update', $record), ['status' => 'present'])->assertOk()->assertJsonPath('record.attended_minutes', 90);
    }

    public function test_summaries_use_monday_sunday_and_academic_term_periods(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection($teacher, 1);
        $student = $section->students()->first();
        foreach ([['2026-08-10', 'present', 60], ['2026-08-11', 'absent', 0], ['2026-07-31', 'present', 60]] as [$date, $status, $minutes]) {
            $session = AttendanceSession::create(['section_id' => $section->id, 'session_date' => $date, 'starts_at' => '08:00', 'ends_at' => '09:00', 'duration_minutes' => 60]);
            AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => $status, 'attended_minutes' => $minutes]);
        }
        $this->actingAs($teacher)->get(route('attendance.sections.index', [$section, 'reference_date' => '2026-08-12']))->assertInertia(fn (Assert $page) => $page->component('attendance/Index')->where('periodSummaries.week.present', 1)->where('periodSummaries.week.absent', 1)->where('periodSummaries.week.rate', 50)->where('periodSummaries.term.sessions', 2)->where('studentSummaries.0.overall.attended_hours', 2));
    }

    public function test_session_page_uses_the_section_student_photo_route(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection($teacher, 1);
        $student = $section->students()->first();
        $student->update(['photo_path' => 'student-photos/example.webp']);
        $session = AttendanceSession::create(['section_id' => $section->id, 'session_date' => '2026-08-10', 'starts_at' => '08:00', 'ends_at' => '09:00', 'duration_minutes' => 60]);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'attended_minutes' => 60]);

        $this->actingAs($teacher)->get(route('attendance.sessions.show', $session))
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/Show')
                ->where('unseated.0.student.photo_url', route('sections.students.photo', [$section, $student])));
    }
}
