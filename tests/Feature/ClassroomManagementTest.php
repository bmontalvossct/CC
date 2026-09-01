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
                ['day_of_week' => 1, 'starts_at' => '08:00', 'ends_at' => '09:00', 'room' => 'Lab 1', 'schedule_type' => 'lecture'],
                ['day_of_week' => 3, 'starts_at' => '08:00', 'ends_at' => '09:00', 'room' => 'Lab 1', 'schedule_type' => 'lecture'],
                ['day_of_week' => 5, 'starts_at' => '13:00', 'ends_at' => '14:30', 'room' => 'Lab 2', 'schedule_type' => 'lab'],
                ['day_of_week' => 6, 'starts_at' => '09:00', 'ends_at' => '12:00', 'room' => 'Lab 2', 'schedule_type' => 'lab'],
            ],
        ]);

        $section = Section::where('subject_code', 'CS 101')->firstOrFail();
        $response->assertRedirect(route('sections.show', $section));
        $this->assertAuthenticatedAs($user);
        $this->get(route('sections.show', $section))->assertOk();
        $this->assertSame(4, $section->schedules()->count());
        $this->assertDatabaseHas('section_schedules', [
            'section_id' => $section->id,
            'day_of_week' => 6,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
            'room' => 'Lab 2',
            'schedule_type' => 'lab',
        ]);
    }

    public function test_schedule_end_time_must_be_after_start_time(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('sections.store'), [
            'subject_code' => 'CS 102',
            'subject_title' => 'Data Structures',
            'name' => 'BSIT 1-B',
            'term' => [
                'name' => 'First Semester',
                'school_year' => '2026-2027',
                'starts_on' => '2026-08-17',
                'ends_on' => '2026-12-18',
            ],
            'schedules' => [
                ['day_of_week' => 1, 'starts_at' => '10:00', 'ends_at' => '09:00', 'schedule_type' => 'lecture'],
            ],
        ]);

        $response->assertSessionHasErrors('schedules.0.ends_at');
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

    public function test_teacher_can_download_roster_csv_template(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);

        $response = $this->actingAs($user)->get(route('sections.students.template', $section));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('student_number,first_name,last_name,middle_name', $response->streamedContent());
    }

    public function test_teacher_can_import_roster_with_success_and_failure_tracking(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);

        $csvContent = "student_number,first_name,last_name,middle_name\n"
            ."2026-001,Alan,Turing,Mathison\n"
            ."2026-002,Margaret,Hamilton,\n"
            .",NoIdStudent,ValidLastName,\n" // Optional student_number - Valid!
            ."2026-004,NoLastName,,\n"        // Missing last_name - Failed
            ."2026-005,,NoFirstName,\n";      // Missing first_name - Failed

        $csv = UploadedFile::fake()->createWithContent('roster.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('sections.students.import', $section), ['roster' => $csv]);

        $response->assertRedirect();
        $response->assertSessionHas('import_results');

        $results = session('import_results');
        $this->assertSame(3, $results['success_count']);
        $this->assertSame(2, $results['failed_count']);
        $this->assertSame(5, $results['total']);
        $this->assertSame('Alan Mathison Turing', $results['successful'][0]['name']);
        $this->assertSame('2026-001', $results['successful'][0]['student_number']);
        $this->assertSame('NoIdStudent ValidLastName', $results['successful'][2]['name']);
        $this->assertSame('—', $results['successful'][2]['student_number']);
        $this->assertSame(5, $results['failed'][0]['row']);
        $this->assertSame(6, $results['failed'][1]['row']);
    }

    public function test_teacher_can_import_roster_with_bom_and_accented_characters(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);

        $csvContent = "\xEF\xBB\xBFstudent_number,first_name,last_name,middle_name\n"
            ."2026-001,Niño,Peña,Dela Cruz\n"
            ."2026-002,José,González,\n";

        $csv = UploadedFile::fake()->createWithContent('roster.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('sections.students.import', $section), ['roster' => $csv]);

        $response->assertRedirect();
        $response->assertSessionHas('import_results');

        $results = session('import_results');
        $this->assertSame(2, $results['success_count']);
        $this->assertSame(0, $results['failed_count']);
        $this->assertDatabaseHas('students', [
            'section_id' => $section->id,
            'student_number' => '2026-001',
            'first_name' => 'Niño',
            'last_name' => 'Peña',
        ]);
    }

    public function test_teacher_can_manually_add_student_without_id_number(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);

        $this->actingAs($user)->post(route('sections.students.store', $section), [
            'student_number' => null,
            'first_name' => 'NoId',
            'last_name' => 'Person',
            'seat_id' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('students', [
            'section_id' => $section->id,
            'first_name' => 'NoId',
            'last_name' => 'Person',
            'student_number' => null,
        ]);
    }

    public function test_sections_index_displays_max_of_6_and_paginates(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => 'First Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
        ]);

        for ($i = 1; $i <= 8; $i++) {
            Section::create([
                'user_id' => $user->id,
                'academic_term_id' => $term->id,
                'subject_code' => "SUBJ 10{$i}",
                'subject_title' => "Subject Title {$i}",
                'name' => "Section {$i}",
            ]);
        }

        $response = $this->actingAs($user)->get(route('sections.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('sections/Index')
            ->has('sections.data', 6)
            ->where('sections.total', 8)
            ->where('sections.per_page', 6)
            ->where('sections.current_page', 1)
            ->where('sections.last_page', 2)
        );

        $page2Response = $this->actingAs($user)->get(route('sections.index', ['page' => 2]));
        $page2Response->assertOk();
        $page2Response->assertInertia(fn ($page) => $page
            ->component('sections/Index')
            ->has('sections.data', 2)
            ->where('sections.total', 8)
            ->where('sections.current_page', 2)
        );
    }

    public function test_teacher_can_view_archived_sections_and_paginate(): void
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => 'First Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
        ]);

        // Create 2 active and 7 archived
        for ($i = 1; $i <= 2; $i++) {
            Section::create([
                'user_id' => $user->id,
                'academic_term_id' => $term->id,
                'subject_code' => "ACT 10{$i}",
                'subject_title' => "Active Subject {$i}",
                'name' => "Active Section {$i}",
            ]);
        }

        for ($i = 1; $i <= 7; $i++) {
            Section::create([
                'user_id' => $user->id,
                'academic_term_id' => $term->id,
                'subject_code' => "ARC 10{$i}",
                'subject_title' => "Archived Subject {$i}",
                'name' => "Archived Section {$i}",
                'archived_at' => now()->subDays($i),
            ]);
        }

        $res = $this->actingAs($user)->get(route('sections.archived'));
        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->component('sections/Archived')
            ->has('sections.data', 6)
            ->where('sections.total', 7)
            ->where('activeCount', 2)
        );
    }

    public function test_teacher_can_restore_archived_section(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $section->update(['archived_at' => now()]);

        $this->actingAs($user)->patch(route('sections.archive', $section))->assertRedirect();

        $this->assertNull($section->fresh()->archived_at);
    }

    public function test_teacher_can_permanently_delete_section_and_associated_data(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);
        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => '2026-999',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->actingAs($user)->delete(route('sections.destroy', $section))->assertRedirect();

        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_teacher_cannot_delete_another_teachers_section(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $section = $this->section($owner);

        $this->actingAs($outsider)->delete(route('sections.destroy', $section))->assertForbidden();

        $this->assertDatabaseHas('sections', ['id' => $section->id]);
    }

    public function test_teacher_can_view_create_section_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('sections.create'));

        $response->assertOk();
    }

    public function test_section_show_orders_students_by_last_name_then_first_name(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);

        Student::create(['section_id' => $section->id, 'student_number' => '2026-001', 'first_name' => 'Zack', 'last_name' => 'Adams']);
        Student::create(['section_id' => $section->id, 'student_number' => '2026-002', 'first_name' => 'Alice', 'last_name' => 'Zuckerberg']);
        Student::create(['section_id' => $section->id, 'student_number' => '2026-003', 'first_name' => 'Bob', 'last_name' => 'Adams']);

        $response = $this->actingAs($user)->get(route('sections.show', $section));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('sections/Show')
            ->where('section.students.0.last_name', 'Adams')
            ->where('section.students.0.first_name', 'Bob')
            ->where('section.students.1.last_name', 'Adams')
            ->where('section.students.1.first_name', 'Zack')
            ->where('section.students.2.last_name', 'Zuckerberg')
            ->where('section.students.2.first_name', 'Alice')
        );
    }

    public function test_teacher_can_edit_student_details_and_seat_assignment(): void
    {
        $user = User::factory()->create();
        $section = $this->section($user);

        $block = $section->layoutBlocks()->create([
            'label' => 'Main',
            'block_row' => 1,
            'block_column' => 1,
            'internal_rows' => 1,
            'internal_columns' => 2,
        ]);
        $seat1 = $block->seats()->create(['row_number' => 1, 'column_number' => 1, 'label' => 'A1']);
        $seat2 = $block->seats()->create(['row_number' => 1, 'column_number' => 2, 'label' => 'A2']);

        $student = Student::create([
            'section_id' => $section->id,
            'student_number' => '2026-0001',
            'first_name' => 'John',
            'middle_name' => 'A.',
            'last_name' => 'Doe',
        ]);
        $seat1->update(['student_id' => $student->id]);

        $response = $this->actingAs($user)->patch(route('sections.students.update', [$section, $student]), [
            'student_number' => '2026-9999',
            'first_name' => 'Johnny',
            'middle_name' => 'Alexander',
            'last_name' => 'Smith',
            'seat_id' => $seat2->id,
        ]);

        $response->assertRedirect();
        $student->refresh();
        $this->assertSame('2026-9999', $student->student_number);
        $this->assertSame('Johnny', $student->first_name);
        $this->assertSame('Alexander', $student->middle_name);
        $this->assertSame('Smith', $student->last_name);
        $this->assertSame('Smith, Johnny Alexander', $student->full_name);

        $this->assertDatabaseHas('seats', ['id' => $seat2->id, 'student_id' => $student->id]);
        $this->assertDatabaseHas('seats', ['id' => $seat1->id, 'student_id' => null]);
    }

    public function test_teacher_can_update_and_remove_student_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $user = User::factory()->create();
        $section = $this->section($user);

        $student = Student::create([
            'section_id' => $section->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $photo = UploadedFile::fake()->image('profile.jpg');
        $response = $this->actingAs($user)->patch(route('sections.students.update', [$section, $student]), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'photo' => $photo,
        ]);
        $response->assertRedirect();
        $student->refresh();
        $this->assertNotNull($student->photo_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($student->photo_path);

        $oldPath = $student->photo_path;
        $response = $this->actingAs($user)->patch(route('sections.students.update', [$section, $student]), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'remove_photo' => true,
        ]);
        $response->assertRedirect();
        $student->refresh();
        $this->assertNull($student->photo_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($oldPath);
    }
}
