<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Listing;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\ConstantService;
use App\Services\BookingPaymentProcessService;
use App\Services\BookingAvailabilityService;

class BookingCreateService
{
    protected ConstantService $constantService;

    protected BookingPaymentProcessService $bookingPaymentProcessService;

    protected BookingAvailabilityService $bookingAvailabilityService;

    public function __construct(
        ConstantService $constantService, 
        BookingPaymentProcessService $bookingPaymentProcessService,
        BookingAvailabilityService $bookingAvailabilityService
    ){
        $this->constantService = $constantService;
        $this->bookingPaymentProcessService = $bookingPaymentProcessService;
        $this->bookingAvailabilityService = $bookingAvailabilityService;
    }

    public function createBooking(array $bookingData, array $paymentData = [])
    {
        \Log::info("Booking data", ['bookingData' => $bookingData, 'paymentData' => $paymentData]);
        DB::beginTransaction();
        try {
            $listing = Listing::findOrFail($bookingData['listing_id']);

            // Availability guard: prevent double bookings even through direct API calls.
            // Lock existing blocking bookings to prevent race conditions, then check.
            Booking::blockingAvailability()
                ->where('listing_id', $listing->id)
                ->where('date_start', '<', $bookingData['end_date'])
                ->where('date_end', '>', $bookingData['start_date'])
                ->lockForUpdate()
                ->get();

            $requestedRooms = is_array($bookingData['rooms']) ? $bookingData['rooms'] : [];
            $availability = $this->bookingAvailabilityService->checkAvailability(
                $listing->id,
                $bookingData['start_date'],
                $bookingData['end_date'],
                $requestedRooms
            );

            if (!$availability['available']) {
                DB::rollBack();
                return [
                    'status' => 'error',
                    'message' => $availability['message'],
                    'code' => 409,
                ];
            }

            $coupon = null;
            if (isset($bookingData['coupon_code'])) {
                $coupon = Coupon::where('code', $bookingData['coupon_code'])->firstOrFail();
            }

            $rooms = $this->bookingPaymentProcessService->normalizeRooms($bookingData['rooms'], $listing->is_entire_place);
            $addons = $this->bookingPaymentProcessService->normalizeAddons($bookingData['addons']);
            $amount = $this->calculateAmount($listing, $rooms, $addons, $coupon, $bookingData['start_date'], $bookingData['end_date']);
            
            // Determine initial booking status based on payment type
            // GCash/GrabPay payments require webhook confirmation, so use 'pending_payment'
            // Card payments are processed immediately, so use default 'to_pay' (will be updated after payment)
            $initialStatus = 'to_pay';
            if (isset($paymentData['payment_type']) && in_array($paymentData['payment_type'], ['gcash', 'grabpay'])) {
                $initialStatus = 'pending_payment';
            }
            
            $booking = $this->bookingPaymentProcessService->createBookingRecord($listing->id, $amount, $bookingData['message'] ?? null, $bookingData['start_date'], $bookingData['end_date'], $coupon->id ?? null, $initialStatus);
            if ($booking) {
                $bookingId = $booking->id;
                $this->bookingPaymentProcessService->addBookingRooms($booking, $rooms);
                $this->bookingPaymentProcessService->addBookingAddons($booking, $addons);

                if (isset($paymentData['payment_type'])) {
                    if ($paymentData['payment_type'] === 'credit/debit_card') {
                        $createBookingPayment = $this->bookingPaymentProcessService->createBookingPayment($paymentData, $bookingId);
                        if ($createBookingPayment['status'] === 'error') {
                            DB::rollBack();
                            return [
                                'status' => 'error',
                                'message' => $createBookingPayment['message'],
                                'code' => $createBookingPayment['code'],
                            ];
                        }

                        if (isset($createBookingPayment['data'])) {
                            $status = $createBookingPayment['data']['data']['data']['attributes']['status'] ?? 'pending';
                            if ($status === 'succeeded') {
                                $paymentStatus = 'paid';
                            } else {
                                $paymentStatus = 'pending';
                            }

                            $paymentIntentId = $createBookingPayment['data']['data']['data']['id'] ?? null;
                            $paymentId = $createBookingPayment['data']['data']['data']['attributes']['payments'][0]['id'] ?? null;
                            $createInvoice = $this->bookingPaymentProcessService->createBookingInvoice(
                                $booking, 
                                $paymentIntentId, 
                                $paymentStatus,
                                $paymentId
                            );
                            if (!$createInvoice) {
                                DB::rollBack();
                                return [
                                    'status' => 'error',
                                    'message' => 'Failed to create booking invoice',
                                    'code' => 500,
                                ];
                            }

                            $paymentMethodValue = $this->bookingPaymentProcessService->convertPaymentMethodValue($paymentData['payment_method']);
                            $updateBookingPaymentData = $this->bookingPaymentProcessService->updateBookingPaymentData(
                                $paymentIntentId, $paymentMethodValue, $paymentStatus
                            );

                            if ($updateBookingPaymentData['status'] === 'error') {
                                DB::rollBack();
                                return [
                                    'status' => 'error',
                                    'message' => $updateBookingPaymentData['message'],
                                    'code' => $updateBookingPaymentData['code'],
                                ];
                            }

                            $getBookingStatus = $this->bookingPaymentProcessService->getBookingStatus($paymentIntentId);
                            $bookingStatus = null;
                            $paymentMethod = null;
                            if ($getBookingStatus['status'] === 'success') {
                                $bookingStatus = $getBookingStatus['booking_status'];
                                $paymentMethod = $getBookingStatus['payment_method'];
                            } else {
                                DB::rollBack();
                                return [
                                    'status' => 'error',
                                    'message' => $getBookingStatus['message'],
                                    'code' => $getBookingStatus['code'],
                                ];
                            }

                            DB::commit();
                            return [
                                'status' => 'success',
                                'message' => 'Booking created successfully',
                                'booking' => $booking,
                                'booking_id' => $booking->id,
                                'booking_status' => $bookingStatus,
                                'payment_method' => $paymentMethod,
                                'code' => 200,
                            ];
                        }
                    } elseif ($paymentData['payment_type'] === 'gcash' || $paymentData['payment_type'] === 'grabpay') {
                        $createBookingPayment = $this->bookingPaymentProcessService->createEPayment($paymentData, $bookingId);
                        if ($createBookingPayment['status'] === 'error') {
                            DB::rollBack();
                            return [
                                'status' => 'error',
                                'message' => $createBookingPayment['message'],
                                'code' => $createBookingPayment['code'],
                            ];
                        }

                        DB::commit();
                        return [
                            'status' => $createBookingPayment['status'],
                            'message' => 'Booking created successfully',
                            'booking' => $booking,
                            'booking_id' => $booking->id,
                            'data' => $createBookingPayment['booking_source'] ?? null,
                            'code' => $createBookingPayment['code'],
                        ];
                    }
                } else {
                    DB::rollBack();
                    return [
                        'status' => 'error',
                        'message' => 'Payment type is required',
                        'code' => 400,
                    ];
                }
            } else {
                DB::rollBack();
                return [
                    'status' => 'error',
                    'message' => 'Failed to create booking',
                    'code' => 500,
                ];
            }
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'status' => 'error',
                'message' => 'Failed to create booking: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }
    public function createBookingInvoice(Booking $booking, string $referenceNumber, string $paymentStatus = 'pending')
    {
        return $booking->invoice()->create([
            'user_id' => $booking->user_id,
            'coupon_id' => $booking->coupon_id,
            'coupon_discount_amount' => $booking->coupon->discount_amount ?? 0,
            'reference_number' => $referenceNumber,
            'payment_status' => $paymentStatus,
        ]);
    }
    public function getBookingNights(string $startDate, $endDate): int
    {
        $nights = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        return max(1, $nights);
    }

    public function calculateAmount(Listing $listing, Collection $rooms, Collection $addons, ?Coupon $coupon, string $startDate, string $endDate): array
    {
        $nights = $this->getBookingNights($startDate, $endDate);

        // Calculate accommodation total using per-night pricing.
        // This iterates each night and applies the correct price (special rate > weekend/weekday).
        // Matches the mobile's calculateEntirePlacePrice / useAggregated per-night logic.
        $accommodationTotal = 0;
        if ($listing->is_entire_place) {
            $accommodationTotal = $listing->calculateTotalForDateRange($startDate, $endDate);
        } else {
            foreach ($rooms as $room) {
                $accommodationTotal += $this->getRoomAmountForDateRange($room, $startDate, $endDate);
            }
        }

        // Calculate addons total (consumable addons are per-night, non-consumable are one-time)
        $addonsTotal = 0;
        foreach ($addons as $addon) {
            $addonsTotal += $this->getAddonsAmountPerNight($addon, $nights);
        }

        // Base = per-night accommodation rate + one-time addon total (used for display reference)
        $perNightAccommodation = $nights > 0 ? ($accommodationTotal / $nights) : $accommodationTotal;
        $perNightAddons = 0;
        foreach ($addons as $addon) {
            $perNightAddons += $this->getAddonAmount($addon);
        }
        $base = $perNightAccommodation + $perNightAddons;

        // Subtotal = accommodation across all nights + all addons
        $subtotal = $accommodationTotal + $addonsTotal;

        // Apply guest service fee (percentage-based, from constants table)
        $guestServiceFeePercentage = $this->getGuestServiceFeePercentage();
        $guestServiceFee = round($subtotal * ($guestServiceFeePercentage / 100), 2);

        // VAT (12% PH) applies on subtotal + service fee (i.e. the full pre-tax amount)
        // e.g. ₱1,000 accommodation + ₱150 service fee = ₱1,150 taxable → VAT = ₱138
        $vatPercentage = $this->getVatPercentage();
        $vat = round(($subtotal + $guestServiceFee) * ($vatPercentage / 100), 2);

        // Total = subtotal + service fee + VAT
        $total = $subtotal + $guestServiceFee + $vat;

        return [
            'total' => $total,
            'subtotal' => $subtotal,
            'base' => $base,
            'guest_service_fee' => $guestServiceFee,
            'guest_service_fee_percentage' => $guestServiceFeePercentage,
            'vat' => $vat,
            'vat_percentage' => $vatPercentage,
        ];
    }

    /**
     * Get the guest service fee percentage from constants table.
     * Default: 15%
     */
    private function getGuestServiceFeePercentage(): float
    {
        try {
            $constant = \App\Models\Constant::where('key', 'guest_service_fee_percentage')->first();
            if ($constant) {
                return (float) $constant->value;
            }
        } catch (\Exception $e) {
            \Log::warning('Could not retrieve guest_service_fee_percentage: ' . $e->getMessage());
        }

        return 15; // Default 15%
    }

    /**
     * Get the VAT percentage from constants table.
     * Default: 12% (Philippines standard VAT rate)
     */
    private function getVatPercentage(): float
    {
        try {
            $constant = \App\Models\Constant::where('key', 'vat_percentage')->first();
            if ($constant) {
                return (float) $constant->value;
            }
        } catch (\Exception $e) {
            \Log::warning('Could not retrieve vat_percentage: ' . $e->getMessage());
        }

        return 12; // Default 12% PH VAT
    }

    private function getRoomAmount($room, string $startDate, string $endDate): float
    {
        return $room->roomCategory->getCurrentPrice($startDate, $endDate) * $room->quantity;
    }

    /**
     * Calculate the total room cost across all nights using per-night pricing.
     * Each night gets the correct price (special rate > weekend/weekday).
     */
    private function getRoomAmountForDateRange($room, string $startDate, string $endDate): float
    {
        return $room->roomCategory->calculateTotalForDateRange($startDate, $endDate) * $room->quantity;
    }

    private function getAddonAmount($addon): float
    {
        return $addon->price * $addon->quantity;
    }

    private function getAddonsAmountPerNight($addon, int $nights): float
    {
        $price = (float) $addon['price'];
        $quantity = (int) $addon['quantity'];
        $isConsumable = !empty($addon['is_consumable']);

        return $price * $quantity * ($isConsumable ? $nights : 1);
    }
}
