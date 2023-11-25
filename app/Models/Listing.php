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
        'saves',
        'views',
    ];

    protected $appends = [
        'average_rating',
        'lowest_room_price',
        'is_liked',
        'is_saved',
        'is_viewed',
    ];

    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating');
    }

    public function getLowestRoomPriceAttribute()
    {
        return $this->roomCategories()->min('price');
    }

    public function getIsLikedAttribute()
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return false;
        }

        return $this->isLikedBy($user);
    }

    public function getIsSavedAttribute()
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return false;
        }

        return $this->isSavedBy($user);
    }

    public function getIsViewedAttribute()
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return false;
        }

        return $this->isViewedBy($user);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function host()
    {
        return $this->user()->withCount([
            'listings',
        ]);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function roomCategories()
    {
        return $this->hasMany(RoomCategory::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function likes()
    {
        return $this->hasMany(ListingLike::class);
    }

    public function saves()
    {
        return $this->hasMany(ListingSave::class);
    }

    public function views()
    {
        return $this->hasMany(ListingView::class);
    }

    public function anonymousViews()
    {
        return $this->hasMany(ListingView::class)->where('user_id', null);
    }

    public function isLikedBy($user)
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function isSavedBy($user)
    {
        return $this->saves()->where('user_id', $user->id)->exists();
    }

    public function isViewedBy($user)
    {
        return $this->views()->where('user_id', $user->id)->exists();
    }
}
