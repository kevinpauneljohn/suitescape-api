<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'user_id',
        'cleanliness',
        'price_affordability',
        'facility_service',
        'comfortability',
        'staff',
        'location',
        'privacy_and_security',
        'accessibility',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
