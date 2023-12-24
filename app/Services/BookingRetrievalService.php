<?php

namespace App\Services;

class BookingRetrievalService
{
    public function getAllBookings()
    {
        $user = auth()->user();

        return $user->bookings()->desc()->with([
            'coupon',
            'bookingRooms.room.listing' => fn ($query) => $query->withAggregate('reviews', 'rating', 'avg'),
            'bookingRooms.room.listing.images',
        ])->get();
    }
}
