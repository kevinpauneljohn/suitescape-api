<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'booking_id',
        'coupon_id',
        'coupon_discount_amount',
        'reference_number',
        'payment_method',
        'payment_status',
        'pending_additional_payments',
        'paid_additional_payments',
    ];

    protected $casts = [
        'pending_additional_payments' => 'collection',
        'paid_additional_payments' => 'collection',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
