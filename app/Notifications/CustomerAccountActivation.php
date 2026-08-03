<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class CustomerAccountActivation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        return (new MailMessage)
            ->subject('Activate your Adzbyte account')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your Adzbyte customer account is ready.')
            ->action('Set password and activate account', $this->activationUrl($notifiable))
            ->line('This secure link expires in 24 hours and can only be used once.');
    }

    public function activationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'account-activation.edit',
            now()->addMinutes((int) config('auth.activation.expire', 1440)),
            [
                'user' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );
    }
}
