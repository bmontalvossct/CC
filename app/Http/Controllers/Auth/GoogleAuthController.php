<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return to_route('login')->withErrors([
                'google' => 'Google sign-in could not be completed. Please try again.',
            ]);
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return to_route('login')->withErrors([
                'google' => 'Your Google account did not provide an email address.',
            ]);
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first()
            ?? User::query()->where('email', $email)->first();

        if ($user && $user->google_id && $user->google_id !== $googleUser->getId()) {
            return to_route('login')->withErrors([
                'google' => 'This email is already linked to a different Google account.',
            ]);
        }

        if ($user) {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'email_verified_at' => now(),
                'google_id' => $googleUser->getId(),
                'password' => Hash::make(Str::random(40)),
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
