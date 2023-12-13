<?php

namespace App\Services;

class BookingRetrievalService
{
    public function getAllBookings()
    {
        $user = auth()->user();

        return $user->bookings->load([
            'coupon',
            'bookingRooms.room' => fn ($query) => $query->withAggregate('reviews', 'rating', 'avg'),
            'bookingRooms.room.listing.images',
        ]);
    }
}
