<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Community extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_name',
        'phone',
        'description',
        'preferred_courts',
        'preferred_days',
        'user_id'
    ];

    protected $casts = [
        'preferred_courts' => 'array',
        'preferred_days' => 'array'
    ];

    /**
     * Get the user that owns the community
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    } 
}
