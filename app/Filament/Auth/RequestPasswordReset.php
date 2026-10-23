<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequest;
use Filament\Notifications\Notification;

class RequestPasswordReset extends BaseRequest
{
    use ScopesPanelPasswordResets;

    public function request(): void
    {
        parent::request();
        $this->form->fill();
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return Notification::make()->title('Check your email')->body('If an account can sign in here, a password reset email will be sent. Check your inbox and spam folder.')->success();
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        return $this->getSentNotification($status);
    }
}
