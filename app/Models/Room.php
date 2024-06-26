<?php

namespace App\Models;

use App\Traits\HasPrices;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory, HasPrices, HasUuids;

    protected $fillable = [
        'listing_id',
        'room_category_id',
    ];

    public function getCurrentBasePrice($date = null)
    {
        return $this->roomCategory->getCurrentBasePrice($date);
    }

    protected static function weekendPriceColumn()
    {
        // Pricing is managed by RoomCategory model
    }

    protected static function weekdayPriceColumn()
    {
        // Pricing is managed by RoomCategory model
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function roomCategory()
    {
        return $this->belongsTo(RoomCategory::class);
    }

    public function roomRule()
    {
        return $this->hasOne(RoomRule::class);
    }

    public function roomAmenities()
    {
        return $this->hasMany(RoomAmenity::class);
    }

    public function specialRates()
    {
        return $this->hasMany(SpecialRate::class);
    }

    public function unavailableDates()
    {
        return $this->hasMany(UnavailableDate::class);
    }

    public function scopeExcludeNoStocks($query)
    {
        return $query->whereHas('roomCategory', function ($query) {
            $query->where('quantity', '>', 0);
        });
    }
}
