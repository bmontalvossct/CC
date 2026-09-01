<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfflineUserAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.offline', false)) {
            if (! Auth::check()) {
                $user = User::query()->first() ?? User::create([
                    'name' => 'Teacher',
                    'username' => 'teacher',
                    'email' => 'teacher@classcheck.local',
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(32)),
                ]);

                Auth::login($user, remember: true);
            }

            // In offline mode, redirect landing and auth pages straight to dashboard
            if ($request->is('/', 'login', 'register', 'forgot-password', 'reset-password/*', 'verify-email', 'confirm-password')) {
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
