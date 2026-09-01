<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\LayoutBlock;
use App\Models\Seat;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_receives_uncompleted_onboarding_milestones(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('onboarding', fn (Assert $onboarding) => $onboarding
                ->where('has_academic_term', false)
                ->where('has_section', false)
                ->where('has_seating_layout', false)
                ->where('has_students', false)
                ->where('has_attendance', false)
                ->where('first_section_id', null)
            )
        );
    }

    public function test_user_with_full_setup_receives_completed_onboarding_milestones(): void
    {
        $user = User::factory()->create();

        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonths(4),
            'is_current' => true,
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'name' => 'Section Acacia',
            'subject_code' => 'CS101',
            'subject_title' => 'Intro to Programming',
            'is_active' => true,
        ]);

        $block = LayoutBlock::create([
            'section_id' => $section->id,
            'label' => 'Center Wing',
            'block_row' => 1,
            'block_column' => 1,
            'internal_rows' => 2,
            'internal_columns' => 2,
        ]);

        Seat::create([
            'layout_block_id' => $block->id,
            'row_number' => 1,
            'column_number' => 1,
            'label' => 'A1',
            'is_disabled' => false,
        ]);

        $student = Student::create([
            'section_id' => $section->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'student_number' => '2026-0001',
            'is_active' => true,
        ]);

        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '09:00:00',
            'duration_minutes' => 60,
        ]);

        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('onboarding', fn (Assert $onboarding) => $onboarding
                ->where('has_academic_term', true)
                ->where('has_section', true)
                ->where('has_seating_layout', true)
                ->where('has_students', true)
                ->where('has_attendance', true)
                ->where('first_section_id', $section->id)
            )
        );
    }

    public function test_user_can_save_onboarding_quick_setup(): void
    {
        $user = User::factory()->create(['name' => 'Initial Name']);

        $response = $this->actingAs($user)->postJson('/onboarding/quick-setup', [
            'name' => 'Prof. Alan Turing',
            'term_name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-31',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'teacher_name' => 'Prof. Alan Turing',
            'term' => [
                'name' => '1st Semester',
                'school_year' => '2026-2027',
            ],
        ]);

        $user->refresh();
        $this->assertEquals('Prof. Alan Turing', $user->name);

        $term = AcademicTerm::where('user_id', $user->id)->first();
        $this->assertNotNull($term);
        $this->assertEquals('1st Semester', $term->name);
        $this->assertEquals('2026-2027', $term->school_year);
        $this->assertTrue($term->is_current);
    }
}
