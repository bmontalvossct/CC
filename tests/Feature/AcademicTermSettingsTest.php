<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicTermSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_view_academic_term_settings_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('academic-term.edit'));

        $response->assertOk();
    }

    public function test_teacher_can_update_universal_academic_semester_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('academic-term.update'), [
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-15',
            'default_starts_at' => '08:30',
            'default_ends_at' => '10:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('academic_terms', [
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
            'default_starts_at' => '08:30',
            'default_ends_at' => '10:00',
        ]);
    }

    public function test_teacher_can_switch_active_term(): void
    {
        $user = User::factory()->create();

        $term1 = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-15',
            'is_current' => true,
        ]);

        $term2 = AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '2nd Semester',
            'school_year' => '2026-2027',
            'starts_on' => '2027-01-10',
            'ends_on' => '2027-05-30',
            'is_current' => false,
        ]);

        $response = $this->actingAs($user)->post(route('academic-term.make-current', $term2));

        $response->assertRedirect();
        $this->assertTrue($term2->fresh()->is_current);
        $this->assertFalse($term1->fresh()->is_current);
    }
}
