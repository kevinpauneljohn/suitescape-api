<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'firstname',
        'middlename',
        'lastname',
        'gender',
        'email',
        'address',
        'zipcode',
        'city',
        'region',
        'mobile_number',
        'password',
        'date_of_birth',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'date_of_birth' => 'date',
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['id'];
    }

    public function fullName()
    {
        return "$this->firstname $this->lastname";
    }

    public function listings()
    {
        return $this->hasMany(Listing::class);
    }

    public function listingsReviews()
    {
        return $this->hasManyThrough(Review::class, Listing::class);
    }

    public function listingsLikes()
    {
        return $this->hasManyThrough(ListingLike::class, Listing::class);
    }

    public function listingsSaves()
    {
        return $this->hasManyThrough(ListingSave::class, Listing::class);
    }

    public function listingsViews()
    {
        return $this->hasManyThrough(ListingView::class, Listing::class);
    }

    public function videos()
    {
        return $this->hasManyThrough(Video::class, Listing::class);
    }

    public function images()
    {
        return $this->hasManyThrough(Image::class, Listing::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function likedListings()
    {
        return $this->hasMany(ListingLike::class);
    }

    public function savedListings()
    {
        return $this->hasMany(ListingSave::class);
    }

    public function viewedListings()
    {
        return $this->hasMany(ListingView::class);
    }
}
