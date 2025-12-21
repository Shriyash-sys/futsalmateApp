<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class VendorEmailOtp extends Notification
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
            ->subject('Your vendor verification code — Futsalmate')
            ->greeting('Hello ' . ($notifiable->name ?? ''))
            ->line('Thanks for signing up as a vendor. Use the following OTP to verify your email:')
            ->line('')
            ->line('**' . $this->otp . '**')
            ->line('This code will expire in ' . $this->expiresInMinutes . ' minutes.')
            ->line('If you did not sign up, ignore this message.');
    }
}
