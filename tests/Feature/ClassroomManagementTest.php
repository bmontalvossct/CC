<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ClassroomManagementTest extends TestCase
{
    use RefreshDatabase;

    private function section(User $user): Section
    {
        $term = AcademicTerm::create(['user_id' => $user->id, 'name' => 'First Semester', 'school_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-20']);

        return Section::create(['user_id' => $user->id, 'academic_term_id' => $term->id, 'subject_code' => 'MATH 101', 'subject_title' => 'Modern Math', 'name' => 'BSIT 1-A']);
    }

    public function test_section_is_private_and_teacher_can_save_a_simple_floor_plan_with_aisles(): void
    {
        $owner = User::factory()->create();
        $section = $this->section($owner);
        $this->actingAs(User::factory()->create())->get(route('sections.show', $section))->assertForbidden();

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => '2026-001',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);
        $oldBlock = $section->layoutBlocks()->create([
            'label' => 'Old block',
            'block_row' => 1,
            'block_column' => 1,
            'internal_rows' => 2,
            'internal_columns' => 2,
        ]);
        $oldBlock->seats()->create([
            'row_number' => 2,
            'column_number' => 2,
            'label' => 'Old-R2-C2',
            'student_id' => $student->id,
        ]);

        $this->actingAs($owner)->put(route('sections.floor-plan.replace', $section), [
            'rows' => 3,
            'columns' => 4,
            'aisle_after_rows' => [1],
            'aisle_after_columns' => [2],
        ])->assertRedirect();

        $block = $section->layoutBlocks()->first();
        $this->assertSame(12, $block->seats()->count());
        $this->assertSame([1], $block->aisle_after_rows);
        $this->assertSame([2], $block->aisle_after_columns);
        $this->assertDatabaseHas('seats', [
            'layout_block_id' => $block->id,
            'label' => 'R2-C2',
            'student_id' => $student->id,
        ]);
    }

    public function test_teacher_can_manually_add_a_student_to_a_selected_chair_or_without_a_chair(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $block = $section->layoutBlocks()->create([
            'label' => 'Classroom',
            'block_row' => 1,
            'block_column' => 1,
            'internal_rows' => 1,
            'internal_columns' => 1,
        ]);
        $seat = $block->seats()->create([
            'row_number' => 1,
            'column_number' => 1,
            'label' => 'R1-C1',
        ]);

        $this->actingAs($user)->post(route('sections.students.store', $section), [
            'student_number' => '2026-101',
            'first_name' => 'Selected',
            'last_name' => 'Student',
            'seat_id' => $seat->id,
        ])->assertRedirect();

        $seatedStudent = $section->students()->where('student_number', '2026-101')->firstOrFail();
        $this->assertSame($seatedStudent->id, $seat->fresh()->student_id);

        $this->actingAs($user)->post(route('sections.students.store', $section), [
            'student_number' => '2026-102',
            'first_name' => 'Unseated',
            'last_name' => 'Student',
            'seat_id' => null,
        ])->assertRedirect();

        $unseatedStudent = $section->students()->where('student_number', '2026-102')->firstOrFail();
        $this->assertNull($unseatedStudent->seat);
    }

    public function test_teacher_can_create_a_section_with_multiple_weekdays_for_each_time_entry(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->post(route('sections.store'), [
            'subject_code' => 'CS 101',
            'subject_title' => 'Introduction to Computing',
            'name' => 'BSIT 1-A',
            'room' => 'Lab 1',
            'term' => [
                'name' => 'First Semester',
                'school_year' => '2026-2027',
                'starts_on' => '2026-08-17',
                'ends_on' => '2026-12-18',
            ],
            'schedules' => [
                ['day_of_week' => 1, 'starts_at' => '08:00', 'ends_at' => '09:00'],
                ['day_of_week' => 3, 'starts_at' => '08:00', 'ends_at' => '09:00'],
                ['day_of_week' => 5, 'starts_at' => '13:00', 'ends_at' => '14:30'],
            ],
        ]);

        $section = Section::where('subject_code', 'CS 101')->firstOrFail();
        $response->assertRedirect(route('sections.show', $section));
        $this->assertSame(3, $section->schedules()->count());
        $this->assertDatabaseHas('section_schedules', [
            'section_id' => $section->id,
            'day_of_week' => 3,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);
    }

    public function test_teacher_can_import_roster_swap_chairs_and_deactivate_without_deleting(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $csv = UploadedFile::fake()->createWithContent('roster.csv', "student_number,first_name,last_name,middle_name\n1,Ada,Lovelace,\n2,Grace,Hopper,B\n");
        $this->actingAs($user)->post(route('sections.students.import', $section), ['roster' => $csv])->assertRedirect();
        $block = $section->layoutBlocks()->create(['label' => 'A', 'block_row' => 1, 'block_column' => 1, 'internal_rows' => 1, 'internal_columns' => 2]);
        $first = $block->seats()->create(['row_number' => 1, 'column_number' => 1, 'label' => 'A-R1-C1', 'student_id' => $section->students()->where('student_number', '1')->value('id')]);
        $second = $block->seats()->create(['row_number' => 1, 'column_number' => 2, 'label' => 'A-R1-C2', 'student_id' => $section->students()->where('student_number', '2')->value('id')]);
        $ada = $first->student;
        $graceId = $second->student_id;
        $this->actingAs($user)->patch(route('sections.students.move', [$section, $ada]), ['seat_id' => $second->id])->assertRedirect();
        $this->assertSame($graceId, $first->fresh()->student_id);
        $this->assertSame($ada->id, $second->fresh()->student_id);
        $this->actingAs($user)->delete(route('sections.students.destroy', [$section, $ada]))->assertRedirect();
        $this->assertDatabaseHas('students', ['id' => $ada->id, 'is_active' => false]);
        $this->assertNull($second->fresh()->student_id);
    }
}
