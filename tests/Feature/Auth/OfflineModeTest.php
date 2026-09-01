<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfflineModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_mode_auto_authenticates_guest_and_redirects_root_to_dashboard(): void
    {
        config(['app.offline' => true]);

        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = auth()->user();
        $this->assertNotNull($user);
        $this->assertSame('Teacher', $user->name);
        $this->assertSame('teacher@classcheck.local', $user->email);
    }

    public function test_offline_mode_redirects_auth_screens_to_dashboard(): void
    {
        config(['app.offline' => true]);

        $this->get('/login')->assertRedirect(route('dashboard'));
        $this->get('/register')->assertRedirect(route('dashboard'));
        $this->get('/forgot-password')->assertRedirect(route('dashboard'));
    }

    public function test_offline_mode_reuses_existing_user_without_duplication(): void
    {
        $existing = User::factory()->create([
            'name' => 'Prof. Alex Cruz',
            'email' => 'alexcruz@university.edu',
        ]);

        config(['app.offline' => true]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::count());
    }

    public function test_offline_mode_shares_is_offline_flag_with_inertia(): void
    {
        config(['app.offline' => true]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('is_offline', true)
        );
    }

    public function test_online_mode_still_requires_login_for_guests(): void
    {
        config(['app.offline' => false]);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_offline_mode_hides_join_url_in_section_show(): void
    {
        $user = User::factory()->create();
        $term = \App\Models\AcademicTerm::create([
            'user_id' => $user->id,
            'name' => '1st Semester',
            'school_year' => '2026-2027',
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonths(4),
            'is_current' => true,
        ]);

        $section = \App\Models\Section::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            'name' => 'Section Acacia',
            'subject_code' => 'CS101',
            'subject_title' => 'Intro to Programming',
            'is_active' => true,
            'enrollment_token' => 'test-token-123',
        ]);

        config(['app.offline' => true]);

        $response = $this->actingAs($user)->get("/sections/{$section->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('sections/Show')
            ->where('is_offline', true)
            ->where('join_url', null)
        );

        config(['app.offline' => false]);

        $onlineResponse = $this->actingAs($user)->get("/sections/{$section->id}");

        $onlineResponse->assertOk();
        $onlineResponse->assertInertia(fn (Assert $page) => $page
            ->component('sections/Show')
            ->where('is_offline', false)
            ->whereNot('join_url', null)
        );
    }
}
