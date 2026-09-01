<?php

namespace App\Providers;

use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            if (str_starts_with((string) config('app.url'), 'https://') || request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https') {
                URL::forceScheme('https');
            }
        }

        SessionGuard::macro('id', function () {
            return $this->user()?->getAuthIdentifier();
        });
    }
}
