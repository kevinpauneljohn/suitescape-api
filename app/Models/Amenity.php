<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    use HasFactory;

    public $fillable = [
        'name',
    ];

    public function roomAmenities()
    {
        return $this->hasMany(RoomAmenity::class);
    }
}
