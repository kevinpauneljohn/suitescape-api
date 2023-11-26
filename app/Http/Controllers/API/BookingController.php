<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Resources\BookingResource;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getAllBookings()
    {
        $user = auth()->user();

        $bookings = $user->bookings()->with('coupon')->get();

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

        return response()->json([
            'booking' => new BookingResource($booking),
            'message' => 'Booking created successfully',
        ]);
    }
}
