<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Requests\UpdateBookingDatesRequest;
use App\Http\Requests\UpdateBookingPaymentStatusRequest;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BookingRoomResource;
use App\Services\BookingCreateService;
use App\Services\BookingRetrievalService;
use App\Services\BookingUpdateService;
use Exception;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    private BookingRetrievalService $bookingRetrievalService;

    private BookingCreateService $bookingCreateService;

    private BookingUpdateService $bookingUpdateService;

    public function __construct(BookingRetrievalService $bookingRetrievalService, BookingCreateService $bookingCreateService, BookingUpdateService $bookingUpdateService)
    {
        $this->middleware('auth:sanctum');

        $this->bookingRetrievalService = $bookingRetrievalService;
        $this->bookingCreateService = $bookingCreateService;
        $this->bookingUpdateService = $bookingUpdateService;
    }

    public function getAllBookings()
    {
        return BookingResource::collection($this->bookingRetrievalService->getAllBookings());
    }

    public function getUserBookings(Request $request)
    {
        // If no user id is provided, default to the authenticated user
        $userId = $request->id ?? auth('sanctum')->id();

        if (! $userId) {
            return response()->json([
                'message' => 'No user id provided.',
            ], 400);
        }

        return BookingResource::collection($this->bookingRetrievalService->getUserBookings($userId));
    }

    public function getHostBookings(Request $request)
    {
        // If no host id is provided, default to the authenticated user
        $hostId = $request->id ?? auth('sanctum')->id();

        if (! $hostId) {
            return response()->json([
                'message' => 'No host id provided.',
            ], 400);
        }

        return BookingResource::collection($this->bookingRetrievalService->getHostBookings($hostId));
    }

    public function getBooking(string $id)
    {
        return new BookingResource($this->bookingRetrievalService->getBooking($id));
    }

    /**
     * @throws Exception
     */
    public function createBooking(CreateBookingRequest $request)
    {
        return response()->json([
            'message' => 'Booking created successfully',
            'booking' => new BookingResource($this->bookingCreateService->createBooking($request->validated())),
        ]);
    }

    public function updateBookingStatus(UpdateBookingStatusRequest $request, string $id)
    {
        return response()->json([
            'message' => 'Booking status updated successfully',
            'booking' => new BookingResource($this->bookingUpdateService->updateBookingStatus($id, $request->validated()['booking_status'])),
        ]);
    }

    public function updateBookingDates(UpdateBookingDatesRequest $request, string $id)
    {
        return response()->json([
            'message' => 'Booking dates updated successfully',
            'booking_room' => new BookingRoomResource($this->bookingUpdateService->updateBookingDates($id, $request->validated()['start_date'], $request->validated()['end_date'])),
        ]);
    }

    public function updateBookingPaymentStatus(UpdateBookingPaymentStatusRequest $request, string $id)
    {
        return response()->json([
            'message' => 'Booking payment status updated successfully',
            'booking' => new BookingResource($this->bookingUpdateService->updateBookingPaymentStatus($id, $request->validated()['payment_status'])),
        ]);
    }
}
