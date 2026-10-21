<?php

namespace App\Providers;

use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        RateLimiter::for('package-offers', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('package-create', fn (Request $request) => [Limit::perMinute(30)->by('all'), Limit::perMinute(10)->by($request->ip())]);
        RateLimiter::for('package-status', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('package-webhook', fn (Request $request) => [Limit::perMinute(300)->by('all'), Limit::perMinute(120)->by($request->ip())]);

        FilamentShield::prohibitDestructiveCommands($this->app->isProduction());

        Password::defaults(fn (): Password => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols());

        RateLimiter::for('account-activation', function (Request $request): Limit {
            $user = $request->route('user');
            $userKey = $user instanceof User ? $user->getKey() : (string) $user;

            return Limit::perMinute(5)->by("{$userKey}|{$request->ip()}");
        });
    }
}
