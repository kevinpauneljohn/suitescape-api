<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Listing extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'location',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function host()
    {
        return $this->user();
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function roomCategories()
    {
        return $this->hasMany(RoomCategory::class);
    }

    public function bookingPolicies()
    {
        return $this->hasMany(BookingPolicy::class);
    }

    public function nearbyPlaces()
    {
        return $this->hasMany(NearbyPlace::class);
    }

    public function serviceRatings()
    {
        return $this->hasMany(ServiceRating::class);
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
