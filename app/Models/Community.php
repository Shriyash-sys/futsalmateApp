<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Community extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'location',
        'phone',
        'members',
        'description',
        'user_id'
    ];

    /**
     * Get the user that owns the community
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
