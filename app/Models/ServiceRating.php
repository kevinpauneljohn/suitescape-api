<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'cleanliness',
        'price_affordability',
        'facility_service',
        'comfortability',
        'staff',
        'location',
        'privacy_and_security',
        'accessibility',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
}
