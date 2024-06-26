<?php

namespace App\Services;

use App\Models\Booking;

class BookingRetrievalService
{
    protected BookingCancellationService $bookingCancellationService;

    protected ConstantService $constantService;

    protected PriceCalculatorService $priceCalculatorService;

    protected Booking $booking;

    public function __construct(BookingCancellationService $bookingCancellationService, ConstantService $constantService, PriceCalculatorService $priceCalculatorService, Booking $booking)
    {
        $this->bookingCancellationService = $bookingCancellationService;
        $this->constantService = $constantService;
        $this->priceCalculatorService = $priceCalculatorService;
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
        return $this->getBookingsQuery()->whereHas('listing', function ($query) use ($hostId) {
            $query->where('user_id', $hostId);
        })->get();
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
            'invoice.invoiceDetails',
            'listing' => fn ($query) => $query->withAggregate('reviews', 'rating', 'avg'),
            'listing.addons',
            'listing.images',
            'listing.bookingPolicies',
        ]);

        // Use the BookingCancellationService to calculate the cancellation fee
        $cancellationFee = $this->bookingCancellationService->calculateCancellationFee($booking);

        // Set booking data properties
        $booking->cancellation_fee = $cancellationFee;
        $booking->suitescape_cancellation_fee = $this->constantService->getConstant('cancellation_fee')->value;
        $booking->cancellation_policy = $this->constantService->getConstant('cancellation_policy')->value;

        return $booking;
    }

    private function getBookingsQuery()
    {
        return $this->booking->desc()->with([
            'coupon',
            'listing' => fn ($query) => $query->withAggregate('reviews', 'rating', 'avg'),
            'listing.host',
            'listing.images',
        ]);
    }
}
