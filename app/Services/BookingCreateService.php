<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Coupon;
use App\Models\Listing;
use App\Models\Room;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BookingCreateService
{
    protected ConstantService $constantService;

    protected UnavailableDateService $unavailableDateService;

    public function __construct(ConstantService $constantService, UnavailableDateService $unavailableDateService)
    {
        $this->constantService = $constantService;
        $this->unavailableDateService = $unavailableDateService;
    }

    /**
     * @throws Exception
     */
    public function createBooking(array $bookingData)
    {
        $isEntirePlace = $this->isEntirePlace($bookingData['listing_id']);
        $rooms = $this->getRooms($bookingData['rooms'], $isEntirePlace);
        $addons = $this->getAddons($bookingData['addons']);
        $coupon = $this->getCoupon($bookingData['coupon_code'] ?? null);
        $amount = $this->calculateAmount($bookingData['listing_id'], $rooms, $addons, $coupon, $bookingData['start_date'], $bookingData['end_date'], $isEntirePlace);
        $booking = $this->createBookingRecord($bookingData['listing_id'], $amount, $bookingData['message'] ?? null, $bookingData['start_date'], $bookingData['end_date'], $coupon->id ?? null);
        $this->addBookingRooms($booking, $rooms, $isEntirePlace);
        $this->addBookingAddons($booking, $addons);

        if ($isEntirePlace) {
            $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'listing', $bookingData['listing_id'], $booking->date_start, $booking->date_end);
        }

        return $booking;
    }

    public function getBookingNights(string $startDate, $endDate): int
    {
        return Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
    }

    private function isEntirePlace(string $listingId): bool
    {
        return Listing::findOrFail($listingId)->is_entire_place;
    }

    /**
     * @throws Exception
     */
    private function getRooms(array $roomsData, bool $isEntirePlace): Collection
    {
        // Get rooms by ids
        $roomIds = array_keys($roomsData);
        $rooms = Room::whereIn('id', $roomIds)->with('roomCategory')->get();

        if (! $isEntirePlace && $rooms->isEmpty()) {
            throw new Exception('No rooms found.');
        }

        // Set quantity for each room
        foreach ($rooms as $room) {
            $room->userQuantity = $roomsData[$room->id];
        }

        return $rooms;
    }

    private function getAddons(array $addonsData): Collection
    {
        // Get addons by ids
        $addonIds = array_keys($addonsData);
        $addons = Addon::whereIn('id', $addonIds)->get();

        // Set quantity for each addon
        foreach ($addons as $addon) {
            $addon->userQuantity = $addonsData[$addon->id];
        }

        return $addons;
    }

    /**
     * @throws Exception
     */
    private function getCoupon(?string $couponCode): ?Coupon
    {
        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();

            if (! $coupon) {
                throw new Exception('Coupon not found.');
            }

            return $coupon;
        }

        return null;
    }

    private function calculateAmount(string $listingId, Collection $rooms, Collection $addons, ?Coupon $coupon, string $startDate, string $endDate, bool $isEntirePlace): float
    {
        $amount = 0;

        if ($isEntirePlace) {
            // Get the entire place price from the Listing model
            $listing = Listing::findOrFail($listingId);

            // Get the price of the listing
            $amount = $listing->getCurrentPrice($startDate, $endDate);
        } else {
            // Go through each room and calculate the total amount
            foreach ($rooms as $room) {
                $amount += $this->getRoomAmount($room, $startDate, $endDate);
            }
        }

        // Add the price of addons
        foreach ($addons as $addon) {
            $amount += $addon->price * $addon->userQuantity;
        }

        // Multiply by nights
        $nights = $this->getBookingNights($startDate, $endDate);
        $amount *= $nights;

        // Apply coupon discount
        //        if ($coupon) {
        //            $amount -= $amount * $coupon->discount / 100;
        //        }

        // Apply 10% discount as example (Make sure to change also in the app)
        $amount -= $amount * 0.1;

        // Add suitescape fee
        $suitescapeFee = $this->constantService->getConstant('suitescape_fee')->value;
        $amount += $suitescapeFee;

        return $amount;
    }

    private function createBookingRecord(string $listingId, float $amount, ?string $message, string $startDate, string $endDate, ?string $couponId)
    {
        $user = auth()->user();

        return $user->bookings()->create([
            'listing_id' => $listingId,
            'coupon_id' => $couponId,
            'amount' => $amount,
            'message' => $message,
            'date_start' => $startDate,
            'date_end' => $endDate,
        ]);
    }

    /**
     * @throws Exception
     */
    private function addBookingRooms($booking, Collection $rooms, bool $isEntirePlace): void
    {
        foreach ($rooms as $room) {
            $booking->bookingRooms()->create([
                'room_id' => $room->id,
                'quantity' => $room->userQuantity,
            ]);

            if (! $isEntirePlace) {
                $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'room', $room->id, $booking->date_start, $booking->date_end);
            }
        }
    }

    private function addBookingAddons($booking, Collection $addons): void
    {
        foreach ($addons as $addon) {
            $booking->bookingAddons()->create([
                'addon_id' => $addon->id,
                'quantity' => $addon->userQuantity,
                'price' => $addon->price * $addon->userQuantity,
            ]);
        }
    }

    private function getRoomAmount($room, string $startDate, string $endDate): float
    {
        return $room->roomCategory->getCurrentPrice($startDate, $endDate) * $room->userQuantity;
    }
}
