<?php

namespace App\Services;

use App\Models\Booking;

class BookingRetrievalService
{
    protected BookingCancellationService $bookingCancellationService;

    protected ConstantService $constantService;

    protected PriceCalculatorService $priceCalculatorService;

    protected UnavailableDateService $unavailableDateService;

    protected Booking $booking;

    public function __construct(BookingCancellationService $bookingCancellationService, ConstantService $constantService, PriceCalculatorService $priceCalculatorService, UnavailableDateService $unavailableDateService, Booking $booking)
    {
        $this->bookingCancellationService = $bookingCancellationService;
        $this->constantService = $constantService;
        $this->priceCalculatorService = $priceCalculatorService;
        $this->unavailableDateService = $unavailableDateService;
        $this->booking = $booking;
    }

    public function getAllBookings()
    {
        return $this->getBookingsQuery()->get();
    }

    public function getUserBookings($userId)
    {
        return $this->getBookingsQuery()->where('user_id', $userId)->get();
    }

    public function getHostBookings($hostId)
    {
        $scopedQuery = Booking::findByHostId($hostId);

        return $this->getBookingsQuery($scopedQuery)->get();
    }

    public function getBooking($id)
    {
        $booking = Booking::find($id);

        $booking->load([
            'bookingAddons.addon',
            'bookingRooms.room.roomCategory' => function ($query) use ($booking) {
                return $this->priceCalculatorService->getPriceForRoomCategoriesToQuery($query, $booking->startDate, $booking->endDate);
            },
            'coupon',
            'invoice',
            'listing' => fn($query) => $query->withAggregate('reviews', 'rating', 'avg'),
            'listing.addons',
            'listing.images',
            'listing.bookingPolicies',
        ]);

        // Set booking data properties
        $booking->is_expired = $this->isBookingExpired($booking);
        $booking->cancellation_fee = $this->bookingCancellationService->calculateCancellationFee($booking);
        $booking->suitescape_cancellation_fee = $this->constantService->getConstant('cancellation_fee')->value;
        $booking->cancellation_policy = $this->constantService->getConstant('cancellation_policy')->value;

        return $booking;
    }

    private function getBookingsQuery($query = null)
    {
        $query = $query ?? $this->booking;

        return $query->desc()->with([
            'coupon',
            'invoice',
            'listing' => fn($query) => $query->withAggregate('reviews', 'rating', 'avg'),
            'listing.host',
            'listing.images',
            'user',
        ]);
    }

    private function isBookingExpired($booking)
    {
        // Check if booking has already passed
        if ($booking->date_end->isPast() && ! $booking->date_end->isToday()) {
            return true;
        }

        // Check if booking dates are blocked
        return $this->unavailableDateService->getUnavailableDatesForBooking($booking, true)->isNotEmpty();
    }
}
