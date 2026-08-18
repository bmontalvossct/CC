<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_view_schedule_calendar(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('schedule.index'));

        $response->assertOk();
    }

    public function test_schedule_calendar_shows_classes_and_conducted_attendance_status(): void
    {
        $user = User::factory()->create();
        $now = Carbon::create(2026, 8, 18);
        Carbon::setTestNow($now);

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
            'subject_code' => 'IT 101',
            'subject_title' => 'Intro to Computing',
            'name' => 'BSIT 1-A',
            'room' => 'Lab 3',
        ]);

        // Tuesday is day_of_week = 2
        $section->schedules()->create([
            'day_of_week' => 2,
            'starts_at' => '08:00',
            'ends_at' => '09:30',
            'room' => 'Lab 3',
            'schedule_type' => 'lab',
        ]);

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => '2026-001',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'is_active' => true,
        ]);

        // Create an attendance session conducted on Aug 18, 2026 (Tuesday)
        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => '2026-08-18',
            'starts_at' => '08:00',
            'ends_at' => '09:30',
            'duration_minutes' => 90,
        ]);

        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        $response = $this->actingAs($user)->get(route('schedule.index', ['month' => '2026-08']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('schedule/Index')
            ->has('calendarDays')
            ->where('stats.total_conducted_month', 1)
        );

        Carbon::setTestNow();
    }
}
