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

    public function calculateCancellationFee(Booking $booking)
    {
        $suitescapeCancellationFee = $this->constantService->getConstant('cancellation_fee')->value;
        $suitescapeFreeCancellationDays = $this->constantService->getConstant('free_cancellation_days')->value;

        $daysSinceBooking = now()->diffInDays($booking->created_at);

        $cancellationFee = 0;
        if ($daysSinceBooking > $suitescapeFreeCancellationDays) {
            // Calculate cancellation fee
            $cancellationFee = ($daysSinceBooking - $suitescapeFreeCancellationDays) * self::CANCELLATION_FEE_PER_DAY;

            // Add suitescape cancellation fee
            $cancellationFee = $cancellationFee + $suitescapeCancellationFee;
        }

        return $cancellationFee;
    }
}
