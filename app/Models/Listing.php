<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Listing extends Model
{
    use HasFactory, HasUuids;

    public $fillable = [
        'user_id',
        'name',
        'location',
        'likes',
    ];

    protected $appends = [
        'average_rating',
        'lowest_room_price',
    ];

    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating');
    }

    public function getLowestRoomPriceAttribute()
    {
        return $this->roomCategories()->min('price');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function roomCategories()
    {
        return $this->hasMany(RoomCategory::class);
    }
}
