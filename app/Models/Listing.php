<?php

namespace App\Models;

use App\Traits\HasPrices;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, HasPrices, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'location',
        'description',
        'facility_type',
        'check_in_time',
        'check_out_time',
        'is_check_in_out_same_day',
        'total_hours',
        'adult_capacity',
        'child_capacity',
        'is_pet_allowed',
        'parking_lot',
        'is_entire_place',
        'entire_place_weekday_price',
        'entire_place_weekend_price',
        'latitude',
        'longitude'
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
    ];

    //    protected $appends = ['entire_place_price'];

    protected static function weekendPriceColumn()
    {
        return 'entire_place_weekend_price';
    }

    protected static function weekdayPriceColumn()
    {
        return 'entire_place_weekday_price';
    }

    protected static function booted(): void
    {
        static::created(function ($listing) {
            if (! $listing->is_entire_place) {
                self::removeEntirePlacePrice($listing);
            }
        });

        static::updated(function ($listing) {
            if (! $listing->is_entire_place) {
                self::removeEntirePlacePrice($listing);
            }
        });
    }

    private static function removeEntirePlacePrice($listing)
    {
        $listing->updateQuietly([
            'entire_place_weekday_price' => null,
            'entire_place_weekend_price' => null,
        ]);
    }

    //    public function getEntirePlacePriceAttribute()
    //    {
    //        return $this->getCurrentPrice();
    //    }

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

    public function specialRates()
    {
        return $this->hasMany(SpecialRate::class);
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

    public function scopeUniqueByLocation($query)
    {
        return $query->whereIn('id', function ($query) {
            $query->selectRaw('MIN(id)')
                ->from('listings')
                ->groupBy('location');
        });
    }
}
