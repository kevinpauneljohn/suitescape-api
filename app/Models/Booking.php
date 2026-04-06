<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'listing_id',
        'coupon_id',
        'amount',
        'base_amount',
        'guest_service_fee',
        'vat',
        'suitescape_fee',
        'host_earnings',
        'message',
        'cancellation_reason',
        'status',
        'date_start',
        'date_end',
        'hold_expires_at',
        'idempotency_key',
        'payment_intent_id',
        'upcoming_reminder_sent_at',
        'review_deadline_passed',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_end' => 'date',
        'hold_expires_at' => 'datetime',
        'upcoming_reminder_sent_at' => 'datetime',
        'review_deadline_passed' => 'boolean',
    ];

    /**
     * Check if this booking is a hold that has expired.
     */
    public function isHoldExpired(): bool
    {
        return $this->status === 'held' && $this->hold_expires_at && $this->hold_expires_at->isPast();
    }

    /**
     * Check if this booking is an active hold.
     */
    public function isActiveHold(): bool
    {
        return $this->status === 'held' && $this->hold_expires_at && $this->hold_expires_at->isFuture();
    }

    /**
     * Scope to get only active holds (not expired).
     */
    public function scopeActiveHolds($query)
    {
        return $query->where('status', 'held')
            ->where('hold_expires_at', '>', now());
    }

    /**
     * Scope to get expired holds.
     */
    public function scopeExpiredHolds($query)
    {
        return $query->where('status', 'held')
            ->where('hold_expires_at', '<=', now());
    }

    /**
     * Scope to get bookings that block availability (active holds + confirmed bookings).
     */
    public function scopeBlockingAvailability($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('status', ['held', 'to_pay', 'pending_payment', 'upcoming', 'ongoing'])
              ->where(function ($q2) {
                  // For held status, only count if not expired
                  $q2->where('status', '!=', 'held')
                     ->orWhere(function ($q3) {
                         $q3->where('status', 'held')
                            ->where('hold_expires_at', '>', now());
                     });
              });
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function host()
    {
        return $this->listing()->user();
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function unavailableDates()
    {
        return $this->hasMany(UnavailableDate::class);
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class);
    }

    public function bookingAddons()
    {
        return $this->hasMany(BookingAddon::class);
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'booking_rooms');
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'booking_addons');
    }

    public function scopeDesc($query)
    {
        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    //    public function getBaseAmount(): float
    //    {
    //        if ($this->listing->is_entire_place) {
    //            return $this->listing->getCurrentPrice($this->date_start, $this->date_end);
    //        }
    //
    //        $baseAmount = 0;
    //
    //        foreach ($this->bookingRooms as $room) {
    //            $baseAmount += $room->price * $room->quantity;
    //        }
    //
    //        foreach ($this->bookingAddons as $addon) {
    //            $baseAmount += $addon->price * $addon->quantity;
    //        }
    //
    //        return $baseAmount;
    //    }

    public static function findByHostId(string $hostId)
    {
        return static::whereHas('listing', function ($query) use ($hostId) {
            $query->where('user_id', $hostId);
        });
    }

    public function cancellations()
    {
        return $this->hasMany(Cancellation::class);
    }

    public function rebookRequests()
    {
        return $this->hasMany(RebookRequest::class);
    }

    public function pendingRebookRequest()
    {
        return $this->hasOne(RebookRequest::class)->where('status', 'pending')->latestOfMany();
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function guestReview()
    {
        return $this->hasOne(GuestReview::class);
    }
}
