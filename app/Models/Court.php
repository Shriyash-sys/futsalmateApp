<?php

namespace App\Models;

use App\Models\Vendor;
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
