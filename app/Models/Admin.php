<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable implements HasName
{
    use HasFactory, HasApiTokens;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'admins';

    /**
     * The guard name for the model.
     *
     * @var string
     */
    protected $guard = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the users associated with this admin.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the name for Filament.
     */
    public function getFilamentName(): string
    {
        return $this->full_name ?? $this->email ?? 'Admin';
    }
}
