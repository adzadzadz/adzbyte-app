<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderReviewUpdated extends Notification
{
    public function __construct(private string $reference, private string $audience) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Adzbyte service request update')->greeting('Hello,')
            ->line('A service request has an update. Sign in to review its current status and conversation.')
            ->action('Review service request', $this->audience === 'admin' ? url('/admin/orders/'.$this->reference) : route('orders.show', ['order' => $this->reference]));
    }
}
