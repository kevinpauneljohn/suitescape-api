<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'listing_id',
        'coupon_id',
        'amount',
        'message',
        'status',
        'date_start',
        'date_end',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_end' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function bookingAddons()
    {
        return $this->hasMany(BookingAddon::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class);
    }

    public function unavailableDates()
    {
        return $this->hasMany(UnavailableDate::class);
    }

    public function scopeDesc($query)
    {
        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    public static function findByHostId(string $hostId)
    {
        return static::whereHas('listing', function ($query) use ($hostId) {
            $query->where('user_id', $hostId);
        });
    }
}
