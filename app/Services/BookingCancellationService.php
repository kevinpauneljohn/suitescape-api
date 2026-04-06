<?php

namespace App\Services;

use App\Models\Booking;

class BookingCancellationService
{
    protected ConstantService $constantService;

    const CANCELLATION_FEE_PER_DAY = 100;

    public function __construct(ConstantService $constantService)
    {
        $this->constantService = $constantService;
    }

    /**
     * Returns only the per-day cancellation fee (excluding the platform/suitescape fee).
     * The platform fee (cancellation_fee constant) is always added separately by callers
     * so it is never double-counted.
     *
     * Within the free_cancellation_days window → returns 0 (no per-day fee).
     * Outside the window → returns (days_over * CANCELLATION_FEE_PER_DAY).
     */
    public function calculateCancellationFee(Booking $booking)
    {
        $suitescapeFreeCancellationDays = $this->constantService->getConstant('free_cancellation_days')->value;

        $daysSinceBooking = now()->diffInDays($booking->created_at);

        if ($daysSinceBooking > $suitescapeFreeCancellationDays) {
            return ($daysSinceBooking - $suitescapeFreeCancellationDays) * self::CANCELLATION_FEE_PER_DAY;
        }

        return 0;
    }
}
