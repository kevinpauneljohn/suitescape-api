<?php

namespace App\Services;

use App\Models\Booking;

class BookingUpdateService
{
    protected BookingCreateService $bookingCreateService;

    protected UnavailableDateService $unavailableDateService;

    public function __construct(BookingCreateService $bookingCreateService, UnavailableDateService $unavailableDateService)
    {
        $this->bookingCreateService = $bookingCreateService;
        $this->unavailableDateService = $unavailableDateService;
    }

    public function updateBookingStatus($id, $status)
    {
        $booking = Booking::findOrFail($id);

        if ($status === 'cancelled') {
            $this->unavailableDateService->removeUnavailableDatesForBooking($booking);
        }

        $booking->update([
            'status' => $status,
        ]);

        return $booking;
    }

    public function updateBookingDates($id, $startDate, $endDate)
    {
        $booking = Booking::findOrFail($id);

        // Update booking room dates
        $booking->update([
            'date_start' => $startDate,
            'date_end' => $endDate,
        ]);

        // Update booking amount
        $this->updateBookingAmount($booking, $startDate, $endDate);

        return $booking;
    }

    private function updateBookingAmount($booking, $startDate, $endDate): void
    {
        $amount = $this->bookingCreateService->calculateAmount(
            $booking->listing,
            $booking->bookingRooms,
            $booking->bookingAddons,
            $booking->coupon,
            $startDate,
            $endDate
        );

        $booking->update([
            'amount' => $amount['total'],
            'base_amount' => $amount['base'],
        ]);
    }
}
