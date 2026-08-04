<?php

namespace App\Providers;

use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\QueueBusy;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentShield::prohibitDestructiveCommands($this->app->isProduction());

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
    }
}
