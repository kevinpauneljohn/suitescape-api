<?php

namespace App\Services;

use App\Models\Booking;

class BookingUpdateService
{
    protected BookingCreateService $bookingCreateService;

    protected BookingDeleteService $bookingDeleteService;

    public function __construct(BookingCreateService $bookingCreateService, BookingDeleteService $bookingDeleteService)
    {
        $this->bookingCreateService = $bookingCreateService;
        $this->bookingDeleteService = $bookingDeleteService;
    }

    public function updateBookingStatus($id, $status)
    {
        $booking = Booking::find($id);

        if ($status === 'cancelled') {
            $this->bookingDeleteService->removeUnavailableDates($booking);
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
        $this->updateBookingAmount($bookingRoom, $nights);

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

    private function updateBookingAmount($bookingRoom, $nights)
    {
        $amount = $this->getBookingAmount($bookingRoom->room, $nights);

        $bookingRoom->booking->update([
            'amount' => $amount,
        ]);
    }

    private function getBookingAmount($room, $nights)
    {
        return $room->roomCategory->price * $nights;
    }
}
