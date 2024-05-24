<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'location',
        'description',
        'facility_type',
        'check_in_time',
        'check_out_time',
        'adult_capacity',
        'child_capacity',
        'is_pet_friendly',
        'parking_lot',
        'is_entire_place',
        'entire_place_price',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function host()
    {
        return $this->user();
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
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

    public function listingNearbyPlaces()
    {
        return $this->hasMany(ListingNearbyPlace::class);
    }

    public function serviceRatings()
    {
        return $this->hasMany(ServiceRating::class);
    }

    public function addons()
    {
        return $this->hasMany(Addon::class);
    }

    public function unavailableDates()
    {
        return $this->hasMany(UnavailableDate::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }

    public function publicImages()
    {
        return $this->images()->where('privacy', 'public');
    }

    public function publicVideos()
    {
        return $this->videos()->where('privacy', 'public');
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

    public function scopeEntirePlace($query)
    {
        return $query->where('is_entire_place', true);
    }

    public function scopeHasMultipleRooms($query)
    {
        return $query->where('is_entire_place', false);
    }
}
