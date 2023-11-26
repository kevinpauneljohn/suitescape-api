<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBookingRequest;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
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
            'booking' => $booking,
            'message' => 'Booking created successfully',
        ]);
    }
}
