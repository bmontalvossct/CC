<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductionReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_creation_reuses_a_term_without_a_nested_first_or_create_transaction(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        AcademicTerm::create([
            'user_id' => $user->id,
            'name' => 'First Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-01',
        ]);

        $this->actingAs($user)->post(route('sections.store'), [
            'subject_code' => 'CS 102',
            'subject_title' => 'Programming',
            'name' => 'BSIT 1-B',
            'term' => [
                'name' => 'First Semester',
                'school_year' => '2026-2027',
                'starts_on' => '2026-08-17',
                'ends_on' => '2026-12-18',
            ],
            'schedules' => [],
        ])->assertRedirect();

        $this->assertSame(1, AcademicTerm::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('academic_terms', [
            'user_id' => $user->id,
            'starts_on' => '2026-08-17',
            'ends_on' => '2026-12-18',
        ]);
        $this->assertDatabaseHas('sections', ['user_id' => $user->id, 'subject_code' => 'CS 102']);
    }

    public function test_dashboard_loads_aggregates_without_per_section_queries(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => 'First Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
        ]);

        foreach (range(1, 6) as $index) {
            $section = Section::create([
                'user_id' => $user->id,
                'academic_term_id' => $term->id,
                'subject_code' => "CS {$index}",
                'subject_title' => 'Computing',
                'name' => "Section {$index}",
            ]);
            $section->students()->create([
                'student_number' => "2026-{$index}",
                'first_name' => 'Student',
                'last_name' => (string) $index,
            ]);
        }

        $lastStudent = $section->students()->firstOrFail();
        $block = $section->layoutBlocks()->create([
            'label' => 'Classroom',
            'block_row' => 1,
            'block_column' => 1,
            'internal_rows' => 1,
            'internal_columns' => 1,
        ]);
        $block->seats()->create([
            'row_number' => 1,
            'column_number' => 1,
            'label' => 'R1-C1',
        ]);
        $meeting = $section->attendanceSessions()->create([
            'session_date' => '2026-08-20',
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'duration_minutes' => 60,
        ]);
        $meeting->records()->create([
            'student_id' => $lastStudent->id,
            'status' => 'present',
            'attended_minutes' => 60,
        ]);
        Section::whereKey($section->id)->update(['updated_at' => '2026-08-14 00:00:00']);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries[] = $query->sql;
            }
        });

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.sections', 6)
                ->where('stats.students', 6)
                ->where('stats.meetings', 1)
                ->where('stats.attendance_rate', 100)
                ->where('sections.0.students', 1)
                ->where('sections.0.seats', 1)
                ->where('sections.0.attendance_rate', 100)
                ->has('sections', 6));

        $this->assertLessThanOrEqual(4, count($queries), 'Dashboard query count grew beyond its fixed aggregate query budget.');
    }
}
