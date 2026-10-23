<?php

namespace App\Filament\Auth;

use App\Actions\Auth\SendPanelEmailVerification;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt as BasePrompt;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use Throwable;

class EmailVerificationPrompt extends BasePrompt
{
    #[Locked]
    public string $deliveryStatus = 'busy';

    public function mount(): void
    {
        parent::mount();
        if (Filament::auth()->check() && ! $this->getVerifiable()->hasVerifiedEmail()) {
            $this->queueVerification();
        }
    }

    private function queueVerification(): void
    {
        try {
            $this->deliveryStatus = app(SendPanelEmailVerification::class)->handle(Filament::auth()->user(), Filament::getCurrentOrDefaultPanel());
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->deliveryStatus = 'failed';
            Log::warning('Verification email could not be queued.', ['exception' => $exception::class]);
        }
    }

    public function resendNotificationAction(): Action
    {
        return Action::make('resendNotification')->link()->label('Send verification email again')->action(function (): void {
            try {
                $this->rateLimit(2);
            } catch (TooManyRequestsException $exception) {
                $this->getRateLimitedNotification($exception)?->send();

                return;
            }
            $this->queueVerification();
        });
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(fn (): string => match ($this->deliveryStatus) {
                'queued', 'recent' => 'A verification email has been queued for '.Filament::auth()->user()->getEmailForVerification().'. Delivery can take a minute. Check your inbox and spam folder, then follow the newest verification link.',
                'failed' => 'We could not queue your verification email. Please try again. If this continues, contact support.',
                'verified' => 'Your email address is already verified.',
                default => 'A verification request is being processed. Wait a moment, then try again.',
            }),
            Text::make(new HtmlString('If you need another email, wait one minute before trying again. '.$this->resendNotificationAction->toHtml())),
        ]);
    }
}
