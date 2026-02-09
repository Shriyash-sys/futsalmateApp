<?php

namespace App\Models;

use App\Models\Vendor;
use App\Models\Book;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Court extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'court_name',
        'location',
        'price',
        'image',
        'facilities',
        'description',
        'status',
        'vendor_id',
        'latitude',
        'longitude',
        'opening_time',
        'closing_time'
    ];


    protected $casts = [
        'availability' => 'array',
        'image' => 'string',
        'facilities' => 'array',
        'latitude' => 'float',
        'longitude' => 'float'
    ];

    public function books()
    {
        return $this->hasMany(Book::class);
    }
}
