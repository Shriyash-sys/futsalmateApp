<?php

namespace App\Notifications;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;

class VendorVerifyEmail extends VerifyEmailBase
{
    /**
     * Get the verification URL for the given notifiable.
     * Uses the vendor verification route name.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        $expiration = Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60));

        return URL::temporarySignedRoute(
            'vendor.verification.verify',
            $expiration,
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->email)]
        );
    }

    public function toMail($notifiable)
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify your vendor account — Futsalmate')
            ->greeting('Hello!', $notifiable->vendor->name)
            ->line('Thanks for signing up as a vendor. Click the button below to verify your email.')
            ->action('Verify Email', $url)
            ->line('If you did not sign up, ignore this message.');
    }
}
