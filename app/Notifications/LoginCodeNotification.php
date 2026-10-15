<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('DAPE-MA login verification code')
            ->greeting('Verify your DAPE-MA sign-in')
            ->line('Use this 6-digit code in the DAPE-MA app to finish signing in:')
            ->line($this->code)
            ->line('This code expires in 10 minutes. If you did not try to sign in, you can ignore this email.');
    }
}
