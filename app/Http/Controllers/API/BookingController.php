<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\BookingRoom;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getAllBookings()
    {
        $user = auth()->user();

        $bookings = $user->bookings()->with([
            'coupon',
            'bookingRooms.room' => fn ($query) => $query->withAggregate('reviews', 'rating', 'avg'),
            'bookingRooms.room.listing',
        ])->get();

        return BookingResource::collection($bookings);
    }

    public function createBooking(CreateBookingRequest $request)
    {
        $user = auth()->user();

        $booking = $user->bookings()->create([
            'coupon_id' => $request->coupon_id,
            'amount' => $request->amount,
            'message' => $request->message,
        ]);

        BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $request->room_id,
            'date_start' => $request->start_date,
            'date_end' => $request->end_date,
        ]);

        return response()->json([
            'booking' => new BookingResource($booking),
            'message' => 'Booking created successfully',
        ]);
    }
}
