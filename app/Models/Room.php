<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory, HasUuids;

    public $fillable = [
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

    public function roomAmenities()
    {
        return $this->hasMany(RoomAmenity::class);
    }
}
