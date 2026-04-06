<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Listing;
use App\Models\Room;
use App\Models\RoomCategory;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class BookingAvailabilityService
{
    /**
     * Check if dates are available for a listing.
     * Returns availability status and details.
     */
    public function checkAvailability(
        string $listingId,
        string $startDate,
        string $endDate,
        array $requestedRooms = [],
        ?string $excludeBookingId = null
    ): array {
        $listing = Listing::with(['rooms.roomCategory'])->findOrFail($listingId);

        if ($listing->is_entire_place) {
            return $this->checkEntirePlaceAvailability($listing, $startDate, $endDate, $excludeBookingId);
        }

        return $this->checkRoomAvailability($listing, $startDate, $endDate, $requestedRooms, $excludeBookingId);
    }

    /**
     * Check availability for entire place listings.
     * Only one booking allowed per date range.
     */
    private function checkEntirePlaceAvailability(
        Listing $listing,
        string $startDate,
        string $endDate,
        ?string $excludeBookingId = null
    ): array {
        $conflictingBookings = Booking::blockingAvailability()
            ->where('listing_id', $listing->id)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q2) use ($startDate, $endDate) {
                    // Overlapping date check
                    $q2->where('date_start', '<', $endDate)
                       ->where('date_end', '>', $startDate);
                });
            })
            ->when($excludeBookingId, function ($q, $excludeBookingId) {
                $q->where('id', '!=', $excludeBookingId);
            })
            ->count();

        if ($conflictingBookings > 0) {
            return [
                'available' => false,
                'message' => 'These dates are already booked or being held by another user.',
                'type' => 'entire_place',
            ];
        }

        return [
            'available' => true,
            'message' => 'Dates are available.',
            'type' => 'entire_place',
        ];
    }

    /**
     * Check availability for room-based listings.
     * Multiple bookings allowed if rooms are available.
     */
    private function checkRoomAvailability(
        Listing $listing,
        string $startDate,
        string $endDate,
        array $requestedRooms,
        ?string $excludeBookingId = null
    ): array {
        if (empty($requestedRooms)) {
            return [
                'available' => true,
                'message' => 'No rooms requested.',
                'type' => 'rooms',
            ];
        }

        $unavailableRooms = [];

        foreach ($requestedRooms as $roomId => $requestedQuantity) {
            $room = Room::with('roomCategory')->find($roomId);
            if (!$room) {
                continue;
            }

            $totalQuantity = $room->roomCategory->quantity;

            // Count how many of this room are booked/held for overlapping dates
            $bookedQuantity = BookingRoom::where('room_id', $roomId)
                ->whereHas('booking', function ($q) use ($startDate, $endDate, $excludeBookingId) {
                    $q->blockingAvailability()
                      ->where(function ($q2) use ($startDate, $endDate) {
                          $q2->where('date_start', '<', $endDate)
                             ->where('date_end', '>', $startDate);
                      })
                      ->when($excludeBookingId, function ($q, $excludeBookingId) {
                          $q->where('id', '!=', $excludeBookingId);
                      });
                })
                ->sum('quantity');

            $availableQuantity = $totalQuantity - $bookedQuantity;

            if ($requestedQuantity > $availableQuantity) {
                $unavailableRooms[] = [
                    'room_id' => $roomId,
                    'room_name' => $room->roomCategory->name,
                    'requested' => $requestedQuantity,
                    'available' => max(0, $availableQuantity),
                    'total' => $totalQuantity,
                ];
            }
        }

        if (!empty($unavailableRooms)) {
            $roomMessages = array_map(function ($r) {
                return "{$r['room_name']}: {$r['available']} of {$r['total']} available (requested {$r['requested']})";
            }, $unavailableRooms);

            return [
                'available' => false,
                'message' => 'Some rooms are not available for the selected dates.',
                'type' => 'rooms',
                'unavailable_rooms' => $unavailableRooms,
                'details' => implode(', ', $roomMessages),
            ];
        }

        return [
            'available' => true,
            'message' => 'All requested rooms are available.',
            'type' => 'rooms',
        ];
    }

    /**
     * Get availability summary for a listing on specific dates.
     */
    public function getAvailabilitySummary(string $listingId, string $startDate, string $endDate): array
    {
        $listing = Listing::with(['rooms.roomCategory'])->findOrFail($listingId);

        if ($listing->is_entire_place) {
            $isAvailable = $this->checkEntirePlaceAvailability($listing, $startDate, $endDate);
            return [
                'type' => 'entire_place',
                'available' => $isAvailable['available'],
            ];
        }

        // For room-based listings, return availability per room category
        $roomCategories = RoomCategory::where('listing_id', $listingId)->get();
        $summary = [];

        foreach ($roomCategories as $category) {
            $rooms = $category->rooms;
            $totalQuantity = $category->quantity;
            $bookedQuantity = 0;

            foreach ($rooms as $room) {
                $bookedQuantity += BookingRoom::where('room_id', $room->id)
                    ->whereHas('booking', function ($q) use ($startDate, $endDate) {
                        $q->blockingAvailability()
                          ->where('date_start', '<', $endDate)
                          ->where('date_end', '>', $startDate);
                    })
                    ->sum('quantity');
            }

            $summary[] = [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'total' => $totalQuantity,
                'booked' => $bookedQuantity,
                'available' => max(0, $totalQuantity - $bookedQuantity),
            ];
        }

        return [
            'type' => 'rooms',
            'room_categories' => $summary,
        ];
    }
}
