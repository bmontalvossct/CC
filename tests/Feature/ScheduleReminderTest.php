<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_today_reminders(): void
    {
        $user = User::factory()->create();
        // 2026-08-18 was a Tuesday (dayOfWeekIso = 2)
        $now = Carbon::create(2026, 8, 18, 7, 40);
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
            'subject_code' => 'CS 101',
            'subject_title' => 'Intro to Programming',
            'name' => 'BSCS 1-A',
            'room' => 'Room 302',
        ]);

        $section->schedules()->create([
            'day_of_week' => 2,
            'starts_at' => '08:00',
            'ends_at' => '09:30',
            'room' => 'Room 302',
            'schedule_type' => 'lecture',
        ]);

        $response = $this->actingAs($user)->getJson(route('schedule.today-reminders'));

        $response->assertOk()
            ->assertJsonStructure([
                'today_date',
                'day_name',
                'current_time',
                'is_holiday',
                'holiday_name',
                'term_name',
                'classes' => [
                    '*' => [
                        'section_id',
                        'section_name',
                        'subject_code',
                        'subject_title',
                        'room',
                        'schedule_type',
                        'starts_at',
                        'ends_at',
                        'starts_at_formatted',
                        'ends_at_formatted',
                        'minutes_until_start',
                        'is_conducted',
                        'attendance_url',
                        'status',
                    ],
                ],
            ]);

        $classes = $response->json('classes');
        $this->assertCount(1, $classes);
        $this->assertEquals('BSCS 1-A', $classes[0]['section_name']);
        $this->assertEquals('CS 101', $classes[0]['subject_code']);
        $this->assertEquals(20, $classes[0]['minutes_until_start']);
        $this->assertEquals('starting_soon', $classes[0]['status']);
        $this->assertFalse($classes[0]['is_conducted']);
    }

    public function test_conducted_class_reflects_conducted_status(): void
    {
        $user = User::factory()->create();
        $now = Carbon::create(2026, 8, 18, 8, 15);
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
            'subject_code' => 'CS 101',
            'subject_title' => 'Intro to Programming',
            'name' => 'BSCS 1-A',
            'room' => 'Room 302',
        ]);

        $section->schedules()->create([
            'day_of_week' => 2,
            'starts_at' => '08:00',
            'ends_at' => '09:30',
            'room' => 'Room 302',
            'schedule_type' => 'lecture',
        ]);

        // Create an attendance session for today
        AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => '2026-08-18',
            'academic_term_id' => $term->id,
            'starts_at' => '08:00',
            'ends_at' => '09:30',
            'duration_minutes' => 90,
        ]);

        $response = $this->actingAs($user)->getJson(route('schedule.today-reminders'));

        $response->assertOk();
        $classes = $response->json('classes');
        $this->assertCount(1, $classes);
        $this->assertTrue($classes[0]['is_conducted']);
        $this->assertEquals('conducted', $classes[0]['status']);
    }

    public function test_notification_settings_page_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('notifications.settings'));
        $response->assertOk();
    }
}
