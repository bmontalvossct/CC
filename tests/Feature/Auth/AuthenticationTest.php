<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_google_callback_creates_and_authenticates_a_verified_user(): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google-user-123');
        $googleUser->shouldReceive('getEmail')->andReturn('teacher@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Class Teacher');

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'teacher@example.com',
            'google_id' => 'google-user-123',
        ]);
        $this->assertNotNull(User::where('email', 'teacher@example.com')->firstOrFail()->email_verified_at);
    }

    public function test_google_callback_links_an_existing_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'teacher@example.com']);
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google-user-456');
        $googleUser->shouldReceive('getEmail')->andReturn($user->email);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-user-456', $user->fresh()->google_id);
    }
}
