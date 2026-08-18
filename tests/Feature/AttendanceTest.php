<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $records = [
            ['2026-07-31', 'present', 60],
            ['2026-08-01', 'present', 60],
            ['2026-08-03', 'absent', 0],
            ['2026-08-09', 'present', 30],
            ['2026-08-10', 'present', 60],
            ['2026-08-11', 'absent', 0],
            ['2026-08-16', 'present', 60],
            ['2026-08-17', 'present', 60],
            ['2026-08-31', 'present', 60],
            ['2026-09-01', 'present', 60],
            ['2026-12-18', 'present', 60],
            ['2026-12-19', 'present', 60],
        ];

        foreach ($records as [$date, $status, $minutes]) {
            $session = AttendanceSession::create(['section_id' => $section->id, 'session_date' => $date, 'starts_at' => '08:00', 'ends_at' => '09:00', 'duration_minutes' => 60]);
            AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => $status, 'attended_minutes' => $minutes]);
        }
        $this->actingAs($teacher)->get(route('attendance.sections.index', [$section, 'reference_date' => '2026-08-12']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/Index')
                ->where('periodSummaries.week.sessions', 3)
                ->where('periodSummaries.week.present', 2)
                ->where('periodSummaries.week.rate', 66.7)
                ->where('periodSummaries.month.sessions', 8)
                ->where('periodSummaries.month.attended_minutes', 330)
                ->where('periodSummaries.term.sessions', 9)
                ->where('periodSummaries.term.present', 7)
                ->where('studentSummaries.0.overall.sessions', 12)
                ->where('studentSummaries.0.overall.attended_hours', 9.5));
    }

    public function test_attendance_index_has_a_fixed_query_budget_for_a_realistic_workload(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection($teacher, 60);
        $now = now();
        $sessions = [];

        foreach (range(0, 79) as $offset) {
            $sessions[] = [
                'section_id' => $section->id,
                'session_date' => today()->subDays($offset)->toDateString(),
                'starts_at' => '08:00',
                'ends_at' => '09:00',
                'duration_minutes' => 60,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        AttendanceSession::insert($sessions);
        $studentIds = $section->students()->pluck('id');
        $attendanceRows = [];

        foreach ($section->attendanceSessions()->pluck('id') as $sessionId) {
            foreach ($studentIds as $studentId) {
                $present = ($studentId + $sessionId) % 5 !== 0;
                $attendanceRows[] = [
                    'attendance_session_id' => $sessionId,
                    'student_id' => $studentId,
                    'status' => $present ? 'present' : 'absent',
                    'attended_minutes' => $present ? 60 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($attendanceRows, 500) as $chunk) {
            AttendanceRecord::insert($chunk);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($teacher)->get(route('attendance.sections.index', $section));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()
            ->assertHeader('Server-Timing')
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/Index')
                ->has('studentSummaries', 60)
                ->has('sessions', 50));
        $this->assertLessThanOrEqual(8, $queryCount, 'Attendance queries must not grow with students or sessions.');
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

    public function test_attendance_index_provides_calendar_data_and_student_absent_late_breakdown(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection($teacher, 2);
        $studentA = $section->students()->first();
        $studentB = $section->students()->skip(1)->first();

        $session1 = AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => '2026-08-10',
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'duration_minutes' => 60,
            'notes' => 'Quiz day',
        ]);
        AttendanceRecord::create(['attendance_session_id' => $session1->id, 'student_id' => $studentA->id, 'status' => 'absent', 'attended_minutes' => 0]);
        AttendanceRecord::create(['attendance_session_id' => $session1->id, 'student_id' => $studentB->id, 'status' => 'late', 'attended_minutes' => 45]);

        $this->actingAs($teacher)->get(route('attendance.sections.index', $section))
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/Index')
                ->has('sessions.0.records', 2)
                ->where('sessions.0.absent_count', 1)
                ->where('sessions.0.late_count', 1)
                ->where('studentSummaries.0.absent_count', 1)
                ->where('studentSummaries.1.late_count', 1)
                ->has('studentSummaries.0.absent_days', 1)
                ->where('studentSummaries.0.absent_days.0.date', '2026-08-10'));
    }

    public function test_attendance_grading_rules_with_3_absence_limit_and_half_points_for_late(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection($teacher, 2);
        $studentA = $section->students()->first();
        $studentB = $section->students()->skip(1)->first();

        // 4 sessions: Student A: 2 present, 1 late, 1 absent -> 2.5/4 pts (62.5%), 2 absences left, status: good
        // Student B: 4 absences -> 0/4 pts (0%), 0 absences left, status: exceeded
        $statuses = [
            ['present', 'absent'],
            ['present', 'absent'],
            ['late', 'absent'],
            ['absent', 'absent'],
        ];

        foreach ($statuses as $idx => [$statusA, $statusB]) {
            $sess = AttendanceSession::create([
                'section_id' => $section->id,
                'session_date' => "2026-08-1{$idx}",
                'starts_at' => '08:00',
                'ends_at' => '09:00',
                'duration_minutes' => 60,
            ]);
            AttendanceRecord::create(['attendance_session_id' => $sess->id, 'student_id' => $studentA->id, 'status' => $statusA, 'attended_minutes' => 60]);
            AttendanceRecord::create(['attendance_session_id' => $sess->id, 'student_id' => $studentB->id, 'status' => $statusB, 'attended_minutes' => 0]);
        }

        $this->actingAs($teacher)->get(route('attendance.sections.index', $section))
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/Index')
                ->where('studentSummaries.0.earned_points', 2.5)
                ->where('studentSummaries.0.possible_points', 4)
                ->where('studentSummaries.0.grade_rate', 62.5)
                ->where('studentSummaries.0.absences_remaining', 2)
                ->where('studentSummaries.0.absence_status', 'good')
                ->where('studentSummaries.1.earned_points', 0)
                ->where('studentSummaries.1.possible_points', 4)
                ->where('studentSummaries.1.grade_rate', 0)
                ->where('studentSummaries.1.absences_remaining', 0)
                ->where('studentSummaries.1.absence_status', 'exceeded'));
    }
}
