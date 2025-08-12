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

    public function cleanUnavailableDates(): void
    {
        $completedBookings = Booking::where('status', 'completed')->get();

        foreach ($completedBookings as $booking) {
            $this->unavailableDateService->removeUnavailableDatesForBooking($booking);
        }
    }

    public function cleanOldCancelledBookings(): void
    {
        $dateLimit = now()->subDays(30);

        Booking::where('status', 'cancelled')
            ->where('created_at', '<', $dateLimit)
            ->delete();
    }

    public function cancelExpiredBookings(): void
    {
        $bookings = Booking::where('status', 'to_pay')
            ->where('created_at', '<', now()->subMinutes(30))
            ->get();

        if ($bookings->isEmpty()) {
            return;
        }

        foreach ($bookings as $booking) {
            $booking->delete();
            $this->unavailableDateService->removeUnavailableDatesForBooking($booking); //to check if there are any unavailable dates to remove
        }
    }
}
