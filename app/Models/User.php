<?php

namespace App\Models;

use App\Models\Admin;
use App\Models\Vendor;
use App\Models\Community;
use App\Models\DeviceToken;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;  


class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'terms',
        'profile_photo',
        'remember',
        'user_type',
        'email_otp',
        'email_otp_expires_at',
        'otp_resend_count',
        'otp_resend_expires_at',
        'fcm_token',
    ];

    /**
     * Relations
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function communities()
    {
        return $this->hasMany(Community::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_otp',
        'email_otp_expires_at',
        'otp_resend_count',
        'otp_resend_expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'terms' => 'boolean',
        'remember' => 'boolean',
        'email_otp_expires_at' => 'datetime',
        'otp_resend_expires_at' => 'datetime'
    ];

    /**
     * Verify the given OTP and mark email verified if valid.
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

    /**
     * Override to send OTP instead of URL verification
     */
    public function sendEmailVerificationNotification()
    {
        $otp = random_int(100000, 999999);
        $expires = now()->addMinutes(config('auth.otp_expire', 10));
        $this->email_otp = (string) $otp;
        $this->email_otp_expires_at = $expires;
        $this->save();
        $this->notify(new \App\Notifications\UserEmailOtp($otp, config('auth.otp_expire', 10)));
    }
}

