<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'listing_id',
        'room_category_id',
    ];

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

    public function unavailableDates()
    {
        return $this->hasMany(UnavailableDate::class);
    }

    public function scopeExcludeZeroQuantity($query)
    {
        return $query->whereHas('roomCategory', function ($query) {
            $query->where('quantity', '>', 0);
        });
    }
}
