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
        $booking = Booking::find($id);

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
        $booking = Booking::find($id);

        // Get first booking room only
        $bookingRoom = $booking->bookingRooms->first();

        // Update booking room dates
        $bookingRoom->update([
            'date_start' => $startDate,
            'date_end' => $endDate,
        ]);

        // Update booking amount
        $nights = $this->bookingCreateService->getBookingNights($startDate, $endDate);
        $this->updateBookingAmount($bookingRoom, $nights, $startDate, $endDate);

        return $bookingRoom;
    }

    public function updateBookingPaymentStatus($id, $status)
    {
        $booking = Booking::find($id);

        $booking->invoice()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'user_id' => $booking->user_id,
                'payment_status' => $status,
            ]
        );

        return $booking;
    }

    private function updateBookingAmount($bookingRoom, $nights, $startDate, $endDate): void
    {
        $amount = $this->getBookingAmount($bookingRoom->room, $nights, $startDate, $endDate);

        $bookingRoom->booking->update([
            'amount' => $amount,
        ]);
    }

    private function getBookingAmount($room, $nights, $startDate, $endDate): float|int
    {
        return $room->roomCategory->getCurrentPrice($startDate, $endDate) * $nights;
    }
}
