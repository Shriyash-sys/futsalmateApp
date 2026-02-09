<?php

namespace App\Models;

use App\Models\Court;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    public function court()
    {
        return $this->hasMany(Court::class);
    }

}
