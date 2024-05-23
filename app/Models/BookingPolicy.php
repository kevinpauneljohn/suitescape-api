<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'name',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
}
