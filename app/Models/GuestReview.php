<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GuestReview extends Model
{
    use HasUuids;

    protected $fillable = [
        'booking_id',
        'guest_id',
        'host_id',
        'rating',
        'content',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function guest()
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }
}
