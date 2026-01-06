<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $guard = 'vendor';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'owner_name',
        'email_otp',
        'email_otp_expires_at',
        'otp_resend_count',
        'otp_resend_expires_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_otp',
        'email_otp_expires_at',
        'otp_resend_count',
        'otp_resend_expires_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_otp_expires_at' => 'datetime',
        'otp_resend_expires_at' => 'datetime'
    ];

    /**
     * Send vendor verification notification using custom notification
     */
    public function sendEmailVerificationNotification()
    {
        // kept for compatibility but now sends OTP via VendorEmailOtp
        $otp = random_int(100000, 999999);
        $expires = now()->addMinutes(config('auth.otp_expire', 10));
        $this->email_otp = (string) $otp;
        $this->email_otp_expires_at = $expires;
        $this->save();
        $this->notify(new \App\Notifications\VendorEmailOtp($otp, config('auth.otp_expire', 10)));
    }

    /**
     * Verify given OTP and mark vendor as verified.
     */
    public function verifyEmailOtp(string $otp): bool
    {
        if (!$this->email_otp || !$this->email_otp_expires_at) {
            return false;
        }

        if ($this->email_otp !== $otp) {
            return false;
        }

        if ($this->email_otp_expires_at->isPast()) {
            return false;
        }

        $this->email_verified_at = now();
        $this->email_otp = null;
        $this->email_otp_expires_at = null;
        $this->save();

        return true;
    }

    public function court()
    {
        return $this->hasMany(Court::class);
    }

}
