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
        'owner_name'
        // 'avatar_url'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Send vendor verification notification using custom notification
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VendorVerifyEmail());
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

}
