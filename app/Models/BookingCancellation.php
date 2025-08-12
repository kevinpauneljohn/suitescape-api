<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingCancellation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'booking_id',
        'user_id',
        'payment_id',
        'refund_id',
        'status',
        'amount',
        'currency',
        'refunded_at',
    ];

    protected $casts = [
        'refunded_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
