<?php

namespace App\Actions\Auth;

use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Panel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

final class SendPanelEmailVerification
{
    public function handle(User $actor, Panel $panel): string
    {
        $connection = config('queue.connections.database.connection') ?? config('database.default');
        if (config('queue.connections.database.driver') !== 'database' || $connection !== config('database.default')) {
            throw new LogicException('Verification requires the database queue on the application database connection.');
        }

        $lock = Cache::lock('auth:verification:lock:'.$actor->getKey(), 10);
        if (! $lock->get()) {
            return 'busy';
        }
        try {
            $cooldownKey = null;
            $result = DB::transaction(function () use ($actor, $panel, &$cooldownKey): string {
                $user = User::lockForUpdate()->findOrFail($actor->getKey());
                if (! $user->canAccessPanel($panel)) {
                    throw new AuthorizationException;
                }
                if ($user->hasVerifiedEmail()) {
                    return 'verified';
                }
                $cooldownKey = 'auth:verification:queued:'.$user->getKey().':'.hash('sha256', $user->getEmailForVerification());
                if (Cache::has($cooldownKey)) {
                    return 'recent';
                }
                $notification = new VerifyEmail;
                $notification->url = $panel->getVerifyEmailUrl($user);
                $user->notify($notification->onConnection('database')->beforeCommit());

                return 'queued';
            });

            if ($result === 'queued') {
                // Start the shared cooldown only after the queued job has committed.
                try {
                    Cache::put($cooldownKey, true, 60);
                } catch (Throwable $exception) {
                    $this->warn('Verification queued, but cooldown storage failed.', $exception);
                }
            }

            return $result;
        } finally {
            // Ancillary cache cleanup must not report a committed email as unqueued.
            try {
                $lock->release();
            } catch (Throwable $exception) {
                $this->warn('Verification lock cleanup failed.', $exception);
            }
        }
    }

    private function warn(string $message, Throwable $exception): void
    {
        try {
            Log::warning($message, ['exception' => $exception::class]);
        } catch (Throwable) {
            // A logging outage must not change the already committed queue result.
        }
    }
}
