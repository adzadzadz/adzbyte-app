<?php

namespace App\Providers;

use App\Actions\Carts\CartActions;
use App\Http\Responses\CustomerLoginResponse;
use App\Models\User;
use App\Services\Carts\CartSession;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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
        $this->app->bind(LoginResponse::class, CustomerLoginResponse::class);
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

        Event::listen(Login::class, function ($event): void {
            $request = request();
            if ($event->guard !== 'web' || ! $event->user instanceof User || ! $request->hasSession()
                || str_starts_with($request->path(), 'admin/')
                || (! $request->routeIs('account-activation.*') && Filament::getCurrentPanel()?->getId() === 'admin')) {
                return;
            }
            app(CartSession::class)->afterAuthentication($request->session(), $event->user, app(CartActions::class));
        });
        Event::listen(Verified::class, function ($event): void {
            $request = request();
            if ($event->user instanceof User && $request->hasSession() && Auth::guard('web')->id() === $event->user->id
                && ! $request->is('admin', 'admin/*') && Filament::getCurrentPanel()?->getId() !== 'admin') {
                app(CartSession::class)->afterAuthentication($request->session(), $event->user, app(CartActions::class));
            }
        });

        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('select 1');
        });

        Event::listen(QueueBusy::class, function (QueueBusy $event): void {
            Log::warning('Queue depth exceeded its configured threshold.', [
                'connection' => $event->connectionName,
                'queue' => $event->queue,
                'size' => $event->size,
            ]);
        });

        Event::listen(JobFailed::class, function (JobFailed $event): void {
            Log::critical('A queued job exhausted its attempts.', [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $event->job->getJobId(),
                'exception' => $event->exception::class,
            ]);
        });

        Password::defaults(fn (): Password => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols());

        RateLimiter::for('account-activation', function (Request $request): Limit {
            $user = $request->route('user');
            $userKey = $user instanceof User ? $user->getKey() : (string) $user;

            return Limit::perMinute(5)->by("{$userKey}|{$request->ip()}");
        });

        RateLimiter::for('api-customer', fn (Request $request): Limit => Limit::perMinute(120)
            ->by('customer-api:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('api-integration', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('integration-api:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('api-webhooks', fn (Request $request): Limit => Limit::perMinute(180)
            ->by('webhook-api:'.$request->ip()));

        RateLimiter::for('api-catalog', fn (Request $request): Limit => Limit::perMinute(120)
            ->by('catalog-api:'.$request->ip()));

        RateLimiter::for('api-storefront', fn (Request $request): array => [
            Limit::perMinute(600)->by('storefront-service:'.$request->user()?->getKey()),
            Limit::perMinute(120)->by('storefront-cart:'.$request->user()?->getKey().'|'.hash('sha256', (string) $request->header('X-Cart-Proof'))),
        ]);

        RateLimiter::for('app-cart', fn (Request $request): Limit => Limit::perMinute(120)
            ->by('app-cart:'.$request->ip()));

        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(60)->by('checkout:'.$request->user()?->id));

        RateLimiter::for('registration', fn (Request $request): array => $request->isMethod('POST') ? [
            Limit::perMinute(5)->by('registration-ip:'.$request->ip()),
            Limit::perHour(3)->by('registration-email:'.hash('sha256', is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : 'invalid')),
        ] : [Limit::perMinute(60)->by('registration-bootstrap:'.$request->ip())]);
    }
}
