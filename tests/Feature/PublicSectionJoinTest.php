<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSectionJoinTest extends TestCase
{
    use RefreshDatabase;

    private function classroom(): array
    {
        $user = User::factory()->create();
        $term = AcademicTerm::create(['user_id' => $user->id, 'name' => 'First Semester', 'school_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-20']);
        $section = Section::create(['user_id' => $user->id, 'academic_term_id' => $term->id, 'subject_code' => 'ENG 101', 'subject_title' => 'Communication', 'name' => 'A', 'enrollment_open' => true]);
        $block = $section->layoutBlocks()->create(['label' => 'A', 'block_row' => 1, 'block_column' => 1, 'internal_rows' => 1, 'internal_columns' => 2]);

        return [$section, $block->seats()->create(['row_number' => 1, 'column_number' => 1, 'label' => 'A-R1-C1'])];
    }

    public function test_public_map_hides_identity_and_claim_stores_private_photo(): void
    {
        Storage::fake('local');
        [$section, $seat] = $this->classroom();
        $this->get(route('join.show', $section->enrollment_token))->assertOk()->assertInertia(fn (Assert $page) => $page->component('join/Show')->missing('section.blocks.0.seats.0.student'));
        $this->post(route('join.store', $section->enrollment_token), ['student_number' => '7', 'first_name' => 'Grace', 'last_name' => 'Hopper', 'seat_id' => $seat->id, 'photo' => UploadedFile::fake()->image('grace.png')])->assertRedirect();
        $student = $section->students()->firstOrFail();
        $this->assertSame($student->id, $seat->fresh()->student_id);
        Storage::disk('local')->assertExists($student->photo_path);
    }

    public function test_closed_enrollment_and_second_claim_are_rejected(): void
    {
        [$section, $seat] = $this->classroom();
        $payload = ['student_number' => '1', 'first_name' => 'First', 'last_name' => 'Student', 'seat_id' => $seat->id];
        $section->update(['enrollment_open' => false]);
        $this->post(route('join.store', $section->enrollment_token), $payload)->assertSessionHasErrors('enrollment');
        $section->update(['enrollment_open' => true]);
        $this->post(route('join.store', $section->enrollment_token), $payload)->assertRedirect();
        $this->post(route('join.store', $section->enrollment_token), [...$payload, 'student_number' => '2'])->assertSessionHasErrors('seat_id');
    }
}
