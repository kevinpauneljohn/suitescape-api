<?php

namespace App\Services;

use App\Mail\BookingCompletedHost;
use App\Mail\BookingCompletedUser;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Listing;
use App\Models\Room;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

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
        $listing = Listing::findOrFail($bookingData['listing_id']);

        $coupon = null;
        if ($bookingData['coupon_code']) {
            $coupon = Coupon::where('code', $bookingData['coupon_code'])->firstOrFail();
        }

        $rooms = $this->normalizeRooms($bookingData['rooms'], $listing->is_entire_place);
        $addons = $this->normalizeAddons($bookingData['addons']);
        $amount = $this->calculateAmount($listing, $rooms, $addons, $coupon, $bookingData['start_date'], $bookingData['end_date']);
        $booking = $this->createBookingRecord($listing->id, $amount, $bookingData['message'] ?? null, $bookingData['start_date'], $bookingData['end_date'], $coupon->id ?? null);

        $this->addBookingRooms($booking, $rooms);
        $this->addBookingAddons($booking, $addons);

        if ($listing->is_entire_place) {
            $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'listing', $listing->id, $booking->date_start, $booking->date_end);
        } else {
            foreach ($rooms as $room) {
                $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'room', $room->id, $booking->date_start, $booking->date_end);
            }
        }

        $this->sendBookingEmails($booking);

        return $booking;
    }

    public function createBookingInvoice(string $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        return $booking->invoice()->create([
            'user_id' => $booking->user_id,
            'coupon_id' => $booking->coupon_id,
            'coupon_discount_amount' => $booking->coupon->discount_amount ?? 0,
            'payment_status' => 'paid',
        ]);
    }

    public function getBookingNights(string $startDate, $endDate): int
    {
        return Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
    }

    public function calculateAmount(Listing $listing, Collection $rooms, Collection $addons, ?Coupon $coupon, string $startDate, string $endDate): array
    {
        $amount = 0;

        if ($listing->is_entire_place) {
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
            $amount += $this->getAddonAmount($addon);
        }

        // The base amount without the nights multiplier and other fees
        $base = $amount;

        // Multiply by nights
        $nights = $this->getBookingNights($startDate, $endDate);
        $amount *= $nights;

        // Apply coupon discount
        //        if ($coupon) {
        //            $amount -= $amount * $coupon->discount_amount / 100;
        //        }

        // Apply 10% discount as example (Make sure to change also in the app)
        $amount -= $amount * 0.1;

        // Add suitescape fee
        $suitescapeFee = $this->constantService->getConstant('suitescape_fee')->value;
        $amount += $suitescapeFee;

        return [
            'total' => $amount,
            'base' => $base,
        ];
    }

    /**
     * @throws Exception
     */
    private function normalizeRooms(array $roomsData, bool $isEntirePlace): Collection
    {
        // Get rooms by ids
        $roomIds = array_keys($roomsData);
        $rooms = Room::whereIn('id', $roomIds)->with('roomCategory')->get();

        // Set room data for each room model
        foreach ($rooms as $room) {
            foreach ($roomsData[$room->id] as $key => $value) {
                $room->$key = $value;
            }
        }

        if (! $isEntirePlace && $rooms->isEmpty()) {
            throw new Exception('No rooms found.');
        }

        return $rooms;
    }

    private function normalizeAddons(array $addonsData): Collection
    {
        // Get addons by ids
        $addonIds = array_keys($addonsData);
        $addons = Addon::whereIn('id', $addonIds)->get();

        // Set addon data for each addon model
        foreach ($addons as $addon) {
            foreach ($addonsData[$addon->id] as $key => $value) {
                $addon->$key = $value;
            }
        }

        return $addons;
    }

    private function createBookingRecord(string $listingId, array $amount, ?string $message, string $startDate, string $endDate, ?string $couponId)
    {
        $user = auth()->user();

        return $user->bookings()->create([
            'listing_id' => $listingId,
            'coupon_id' => $couponId,
            'amount' => $amount['total'],
            'base_amount' => $amount['base'],
            'message' => $message,
            'date_start' => $startDate,
            'date_end' => $endDate,
        ]);
    }

    private function addBookingRooms($booking, Collection $rooms): void
    {
        foreach ($rooms as $room) {
            $booking->bookingRooms()->create([
                'room_id' => $room->id,
                'name' => $room->name,
                'quantity' => $room->quantity,
                'price' => $room->roomCategory->getCurrentPrice($booking->date_start, $booking->date_end),
            ]);
        }
    }

    private function addBookingAddons($booking, Collection $addons): void
    {
        foreach ($addons as $addon) {
            $booking->bookingAddons()->create([
                'addon_id' => $addon->id,
                'name' => $addon->name,
                'quantity' => $addon->quantity,
                'price' => $addon->price,
            ]);
        }
    }

    private function getRoomAmount($room, string $startDate, string $endDate): float
    {
        return $room->roomCategory->getCurrentPrice($startDate, $endDate) * $room->quantity; // Quantity is either from Booking<Model> or from the normalized <model>
    }

    private function getAddonAmount($addon): float
    {
        return $addon->price * $addon->quantity;
    }

    private function sendBookingEmails($booking)
    {
        // Send email to the host
        Mail::to($booking->listing->user->email)->send(new BookingCompletedHost($booking));

        // Send email to the user
        Mail::to($booking->user->email)->send(new BookingCompletedUser($booking));
    }
}
