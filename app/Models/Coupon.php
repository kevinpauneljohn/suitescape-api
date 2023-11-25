<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'code',
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
