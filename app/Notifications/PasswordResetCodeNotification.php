<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $resetUrl,
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
            ->subject('DAPE-MA password reset code')
            ->greeting('Reset your DAPE-MA password')
            ->line('Use this 6-digit code in the DAPE-MA app to continue:')
            ->line($this->code)
            ->line('You can also reset your password in the browser:')
            ->action('Reset password', $this->resetUrl)
            ->line('This code expires in 60 minutes. If you did not request a reset, you can ignore this email.');
    }
}
