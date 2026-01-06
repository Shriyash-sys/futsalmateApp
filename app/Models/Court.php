<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
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
