<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use A6digital\Image\DefaultProfileImage;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Propaganistas\LaravelPhone\Casts\E164PhoneNumberCast;
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
        'picture',
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
        'mobile_number' => E164PhoneNumberCast::class.':PH',
        'password' => 'hashed',
        'date_of_birth' => 'date',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function ($user) {
            self::generateDefaultProfileImage($user);
        });

        static::updated(function ($user) {
            if ($user->wasChanged(['firstname', 'lastname'])) {
                self::generateDefaultProfileImage($user);
            }
        });
    }

    /**
     * Generate a default profile image for the user.
     *
     * @throws Exception
     */
    private static function generateDefaultProfileImage($user): void
    {
        $filename = 'default-'.$user->firstname[0].$user->lastname[0].'.png';

        // Check if the image does not exist
        if (! Storage::exists('public/images/'.$filename)) {
            // Generate random color using Faker
            $faker = Faker::create();
            $color = $faker->hexColor;

            // Generate the default profile image
            $img = DefaultProfileImage::create($user->firstname[0].' '.$user->lastname[0], 512, $color);

            // Save the image to the storage
            Storage::put('public/images/'.$filename, $img->encode());
        }

        // Set the filename to the user
        $user->fill(['picture' => $filename])->saveQuietly();
    }

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
