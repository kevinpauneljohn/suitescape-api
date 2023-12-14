<?php

namespace App\Services;

class BookingCreateService
{
    public function createBooking(array $bookingData)
    {
        $user = auth()->user();

        $booking = $user->bookings()->create([
            'coupon_id' => $bookingData['coupon_id'] ?? null,
            'amount' => $bookingData['amount'],
            'message' => $bookingData['message'],
        ]);

        $booking->bookingRooms()->create([
            'room_id' => $bookingData['room_id'],
            'date_start' => $bookingData['start_date'],
            'date_end' => $bookingData['end_date'],
        ]);
        //        BookingRoom::create([
        //            'booking_id' => $booking->id,
        //            'room_id' => $request->room_id,
        //            'date_start' => $request->start_date,
        //            'date_end' => $request->end_date,
        //        ]);

        return $booking;
    }
}
