<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpecialRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'room_id',
        'title',
        'price',
        'start_date',
        'end_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
