<?php

namespace App\Services;

use App\Models\Booking;

class BookingDeleteService
{
    public function removeUnavailableDates(Booking $booking)
    {
        $booking->unavailableDates()->delete();
    }

    public function cleanUnavailableDates()
    {
        $completedBookings = Booking::where('status', 'completed')->get();

        foreach ($completedBookings as $booking) {
            $this->removeUnavailableDates($booking);
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
