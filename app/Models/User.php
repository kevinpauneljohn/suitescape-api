<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use A6digital\Image\DefaultProfileImage;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Propaganistas\LaravelPhone\Casts\E164PhoneNumberCast;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
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
        'profile_image',
        'cover_image',
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
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'full_name',
        'profile_image_url',
        'cover_image_url',
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
            if ($user->wasChanged(['firstname', 'lastname']) && str_starts_with($user->profile_image, 'default-')) {
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
        // Split the name
        $splitName = explode(' ', $user->fullname);
        $firstName = $splitName[0];
        $lastName = end($splitName);

        // Set the filename
        $filename = 'default-'.$firstName[0].$lastName[0].'.png';

        // Check if the image does not exist
        if (Storage::disk('public')->missing('avatars/'.$filename)) {
            // Generate random color using Faker
            $faker = Faker::create();
            $color = $faker->hexColor;

            // Generate the default profile image
            $img = DefaultProfileImage::create($user->firstname[0].' '.$user->lastname[0], 512, $color);

            // Save the image to the storage
            Storage::disk('public')->put('avatars/'.$filename, $img->encode());
        }

        // Set the filename to the user
        $user->fill(['profile_image' => $filename])->saveQuietly();
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

    public function getFullNameAttribute()
    {
        return "$this->firstname $this->lastname";
    }

    public function getProfileImageUrlAttribute()
    {
        return $this->profile_image ? Storage::url('avatars/'.$this->profile_image) : null;
    }

    public function getCoverImageUrlAttribute()
    {
        return $this->cover_image ? Storage::url('covers/'.$this->cover_image) : null;
    }

    public function chats()
    {
        return $this->belongsToMany(Chat::class);
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

    public function activeSessions()
    {
        return $this->hasMany(ActiveSession::class);
    }

    public function isActive()
    {
        if ($this->relationLoaded('activeSessions')) {
            return $this->activeSessions->isNotEmpty();
        }

        return $this->activeSessions()->exists();
    }

    public function createActiveSession($deviceId, $deviceName = null)
    {
        $activeSession = $this->activeSessions()->where('device_id', $deviceId)->first();

        if ($activeSession) {
            return $activeSession;
        }

        return $this->activeSessions()->create([
            'device_id' => $deviceId,
            'device_name' => $deviceName,
        ]);
    }

    public function deleteActiveSession($deviceId)
    {
        $activeSession = $this->activeSessions()->where('device_id', $deviceId)->first();

        if (! $activeSession) {
            return false;
        }

        $activeSession->delete();

        return true;
    }
}
