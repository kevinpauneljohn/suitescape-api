<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'name',
        'description',
        'floor_area',
        'type_of_beds',
        'pax',
        'price',
        'quantity',
    ];

    protected $casts = [
        'type_of_beds' => 'array',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

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

    public static function getMinPriceQuery($listingId)
    {
        return self::select('price')
            ->where('listing_id', $listingId)
            ->orderBy('price')
            ->limit(1);
    }
}
