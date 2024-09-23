<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Requests\DateRangeRequest;
use App\Http\Requests\FutureDateRangeRequest;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BookingRoomResource;
use App\Models\Booking;
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

    /**
     * Get All Bookings
     *
     * Retrieves a collection of all bookings.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllBookings()
    {
        return BookingResource::collection($this->bookingRetrievalService->getAllBookings());
    }

    /**
     * Get User Bookings
     *
     * Retrieves bookings for a specific user.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
     */
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

    /**
     * Get Host Bookings
     *
     * Retrieves bookings for a specific host.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
     */
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

    /**
     * Get Booking
     *
     * Retrieves a specific booking by ID.
     *
     * @return BookingResource
     */
    public function getBooking(string $id)
    {
        return new BookingResource($this->bookingRetrievalService->getBooking($id));
    }

    public function getBookingAmount(DateRangeRequest $request, string $id)
    {
        $booking = Booking::findOrFail($id);

        $amount = $this->bookingCreateService->calculateAmount(
            $booking->listing,
            $booking->bookingRooms,
            $booking->bookingAddons,
            $booking->coupon,
            $request->validated()['start_date'],
            $request->validated()['end_date'],
        );

        return response()->json([
            'amount' => $amount['base'],
        ]);
    }

    /**
     * Create Booking
     *
     * Creates a new booking.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws Exception
     */
    public function createBooking(CreateBookingRequest $request)
    {
        return response()->json([
            'message' => 'Booking created successfully',
            'booking' => new BookingResource($this->bookingCreateService->createBooking($request->validated())),
        ]);
    }

    /**
     * Update Booking Status
     *
     * Updates the status of a booking.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBookingStatus(UpdateBookingStatusRequest $request, string $id)
    {
        return response()->json([
            'message' => 'Booking status updated successfully',
            'booking' => new BookingResource($this->bookingUpdateService->updateBookingStatus($id, $request->validated()['booking_status'])),
        ]);
    }

    /**
     * Update Booking Dates
     *
     * Updates the start and end dates of a booking.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBookingDates(FutureDateRangeRequest $request, string $id)
    {
        return response()->json([
            'message' => 'Booking dates updated successfully',
            'booking_room' => new BookingRoomResource($this->bookingUpdateService->updateBookingDates($id, $request->validated()['start_date'], $request->validated()['end_date'])),
        ]);
    }
}
