<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'label',
        'code',
        'discount_amount',
        'activated_date',
        'expiry_date',
        'quantity',
        'measurement',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function booking()
    {
        return $this->hasOne(Booking::class);
    }
}
