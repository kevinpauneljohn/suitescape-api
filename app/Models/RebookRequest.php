<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RebookRequest extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    const EXPIRY_HOURS = 12;

    protected $fillable = [
        'booking_id',
        'requested_by',
        'requested_date_start',
        'requested_date_end',
        'reason',
        'status',
        'original_amount',
        'new_amount',
        'difference',
        'new_base_amount',
        'new_guest_service_fee',
        'new_vat',
        'guest_service_fee_percentage',
        'vat_percentage',
        'host_note',
        'responded_at',
        'expires_at',
        'epayment_source_id',
        'rebook_payment_id',
        'payment_status',
    ];

    protected $casts = [
        'requested_date_start' => 'date',
        'requested_date_end'   => 'date',
        'responded_at'         => 'datetime',
        'expires_at'           => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    // Convenience: nights in the requested range
    public function getRequestedNightsAttribute(): int
    {
        return max(1, $this->requested_date_start->diffInDays($this->requested_date_end));
    }

    // True when the request is still pending AND past its expiry time
    public function getIsExpiredAttribute(): bool
    {
        return $this->status === 'pending'
            && $this->expires_at !== null
            && now()->isAfter($this->expires_at);
    }

    // Seconds remaining before expiry (0 if already expired or not pending)
    public function getSecondsUntilExpiryAttribute(): int
    {
        if ($this->status !== 'pending' || $this->expires_at === null) {
            return 0;
        }
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
                     ->where('expires_at', '<=', now());
    }

    public function scopeActive($query)
    {
        // Pending and not yet expired
        return $query->where('status', 'pending')
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }
}
