<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'court_name',
        'location',
        'price',
        'image',
        'description',
        'status',
        'vendor_id',
        'latitude',
        'longitude'
    ];

    protected $casts = [
        'availability' => 'array',
        'image' => 'string',
        'latitude' => 'float',
        'longitude' => 'float'
    ];

    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }
}
