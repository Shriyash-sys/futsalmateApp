<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class UserEmailOtp extends Notification
{
    protected $otp;
    protected $expiresInMinutes;

    public function __construct($otp, $expiresInMinutes = 10)
    {
        $this->otp = $otp;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your verification code — Futsalmate')
            ->greeting('Hello ' . ($notifiable->full_name ?? ''))
            ->line('Thank you for signing up. Use the following OTP to verify your email:')
            ->line('')
            ->line('**' . $this->otp . '**')
            ->line('This code will expire in ' . $this->expiresInMinutes . ' minutes.')
            ->line('If you did not sign up, ignore this message.');
    }
}
