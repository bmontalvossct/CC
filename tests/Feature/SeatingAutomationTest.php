<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatingAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_auto_assign_unseated_students_alphabetically(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-01',
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS 101',
            'subject_title' => 'Intro',
            'name' => 'Section A',
        ]);

        $block = $section->layoutBlocks()->create([
            'label' => 'Classroom',
            'block_row' => 1,
            'block_column' => 1,
            'internal_rows' => 2,
            'internal_columns' => 2,
        ]);

        $seat1 = $block->seats()->create(['row_number' => 1, 'column_number' => 1, 'label' => 'R1-C1']);
        $seat2 = $block->seats()->create(['row_number' => 1, 'column_number' => 2, 'label' => 'R1-C2']);

        $studentZ = $section->students()->create(['student_number' => '001', 'first_name' => 'Zoe', 'last_name' => 'Zack']);
        $studentA = $section->students()->create(['student_number' => '002', 'first_name' => 'Alice', 'last_name' => 'Adams']);

        $this->actingAs($user)->post(route('sections.seats.auto-assign', $section), ['mode' => 'alphabetical'])
            ->assertRedirect();

        $seat1->refresh();
        $seat2->refresh();

        // Alice Adams should be seated first
        $this->assertEquals($studentA->id, $seat1->student_id);
        $this->assertEquals($studentZ->id, $seat2->student_id);
    }

    public function test_teacher_can_reassign_already_seated_students_alphabetically(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-01',
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS 101',
            'subject_title' => 'Intro',
            'name' => 'Section A',
        ]);

        $block = $section->layoutBlocks()->create([
            'label' => 'Classroom',
            'block_row' => 1,
            'block_column' => 1,
            'internal_rows' => 2,
            'internal_columns' => 2,
        ]);

        $studentZ = $section->students()->create(['student_number' => '001', 'first_name' => 'Zoe', 'last_name' => 'Zack']);
        $studentA = $section->students()->create(['student_number' => '002', 'first_name' => 'Alice', 'last_name' => 'Adams']);

        // Reverse seated originally
        $seat1 = $block->seats()->create(['row_number' => 1, 'column_number' => 1, 'label' => 'R1-C1', 'student_id' => $studentZ->id]);
        $seat2 = $block->seats()->create(['row_number' => 1, 'column_number' => 2, 'label' => 'R1-C2', 'student_id' => $studentA->id]);

        $this->actingAs($user)->post(route('sections.seats.auto-assign', $section), ['mode' => 'alphabetical'])
            ->assertRedirect();

        $seat1->refresh();
        $seat2->refresh();

        // After auto-assign by last name, Adams in seat 1, Zack in seat 2
        $this->assertEquals($studentA->id, $seat1->student_id);
        $this->assertEquals($studentZ->id, $seat2->student_id);
    }

    public function test_teacher_can_reset_all_seats(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-01',
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS 101',
            'subject_title' => 'Intro',
            'name' => 'Section A',
        ]);

        $block = $section->layoutBlocks()->create([
            'label' => 'Classroom',
            'block_row' => 1,
            'block_column' => 1,
            'internal_rows' => 1,
            'internal_columns' => 1,
        ]);

        $student = $section->students()->create(['student_number' => '001', 'first_name' => 'Alice', 'last_name' => 'Adams']);
        $seat = $block->seats()->create(['row_number' => 1, 'column_number' => 1, 'label' => 'R1-C1', 'student_id' => $student->id]);

        $this->actingAs($user)->post(route('sections.seats.reset', $section))
            ->assertRedirect();

        $seat->refresh();
        $this->assertNull($seat->student_id);
    }

    public function test_teacher_can_view_printable_roster(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-01',
        ]);

        $section = Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'subject_code' => 'CS 101',
            'subject_title' => 'Intro',
            'name' => 'Section A',
        ]);

        $this->actingAs($user)->get(route('sections.roster.print', $section))
            ->assertOk();
    }
}
