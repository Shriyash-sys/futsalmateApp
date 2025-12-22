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
        'vendor_id'
    ];

    protected $casts = [
        'availability' => 'array',
        'image' => 'array',
    ];

    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }
}
