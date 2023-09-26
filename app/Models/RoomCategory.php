<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomCategory extends Model
{
    use HasFactory;

    public $fillable = [
        'listing_id',
        'name',
        'size',
        'type_of_beds',
        'pax',
        'price',
        'tax',
    ];

    protected $casts = [
        'type_of_beds' => 'array',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function roomAmenities()
    {
        return $this->hasManyThrough(RoomAmenity::class, Room::class);
    }
}
