<?php

namespace App\Models;

use App\Traits\HasPrices;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomCategory extends Model
{
    use HasFactory, HasPrices;

    protected $fillable = [
        'listing_id',
        'name',
        'description',
        'floor_area',
        'type_of_beds',
        'pax',
        'weekday_price',
        'weekend_price',
        'quantity',
    ];

    protected $casts = [
        'type_of_beds' => 'array',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    //    protected $appends = ['price'];

    protected static function weekendPriceColumn()
    {
        return 'weekend_price';
    }

    protected static function weekdayPriceColumn()
    {
        return 'weekday_price';
    }

    //    public function getPriceAttribute()
    //    {
    //        return $this->getCurrentPrice();
    //    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function roomAmenities()
    {
        return $this->hasManyThrough(RoomAmenity::class, Room::class);
    }

    public function specialRates()
    {
        return $this->hasManyThrough(SpecialRate::class, Room::class);
    }
}
