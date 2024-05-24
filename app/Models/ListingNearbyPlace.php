<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListingNearbyPlace extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'nearby_place_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function nearbyPlace()
    {
        return $this->belongsTo(NearbyPlace::class);
    }
}
