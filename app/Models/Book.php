<?php

namespace App\Models;

use App\Models\User;
use App\Models\Court;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = [
        'date',
        'start_time',
        'end_time',
        'notes',
        'customer_name',
        'customer_phone',
        'payment',
        'payment_status',
        'price',
        'status',
        'transaction_uuid',
        'user_id',
        'court_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * Check if the booking is paid
     */
    public function isPaid()
    {
        return $this->payment_status === 'Paid';
    }

    /**
     * Check if the booking is confirmed
     */
    public function isConfirmed()
    {
        return $this->status === 'Confirmed';
    }

    /**
     * Check if the booking is pending payment
     */
    public function isRejected()
    {
        return $this->status === 'Rejected';
    }

    /**
     * Get the payment method display name
     */
    public function getPaymentMethodDisplayName()
    {
        return match($this->payment) {
            'eSewa' => 'eSewa Digital Payment',
            'Cash' => 'Cash Payment',
            default => $this->payment
        };
    }
}
