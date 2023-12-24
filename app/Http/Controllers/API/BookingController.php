<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Services\BookingCreateService;
use App\Services\BookingRetrievalService;

class BookingController extends Controller
{
    private BookingRetrievalService $bookingRetrievalService;

    private BookingCreateService $bookingCreateService;

    public function __construct(BookingRetrievalService $bookingRetrievalService, BookingCreateService $bookingCreateService)
    {
        $this->middleware('auth:sanctum');

        $this->bookingRetrievalService = $bookingRetrievalService;
        $this->bookingCreateService = $bookingCreateService;
    }

    public function getAllBookings()
    {
        return BookingResource::collection($this->bookingRetrievalService->getAllBookings());
    }

    public function createBooking(CreateBookingRequest $request)
    {
        return response()->json([
            'booking' => new BookingResource($this->bookingCreateService->createBooking($request->validated())),
            'message' => 'Booking created successfully',
        ]);
    }
}
