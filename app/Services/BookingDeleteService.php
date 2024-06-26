<?php

namespace App\Services;

use App\Models\Booking;

class BookingDeleteService
{
    protected UnavailableDateService $unavailableDateService;

    public function __construct(UnavailableDateService $unavailableDateService)
    {
        $this->unavailableDateService = $unavailableDateService;
    }

    public function cleanUnavailableDates()
    {
        $completedBookings = Booking::where('status', 'completed')->get();

        foreach ($completedBookings as $booking) {
            $this->unavailableDateService->removeUnavailableDatesForBooking($booking);
        }
    }

    public function cleanOldCancelledBookings()
    {
        $dateLimit = now()->subDays(30);

        Booking::where('status', 'cancelled')
            ->where('created_at', '<', $dateLimit)
            ->delete();
    }
}
