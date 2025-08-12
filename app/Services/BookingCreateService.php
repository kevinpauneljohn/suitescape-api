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

class BookingCreateService
{
    protected ConstantService $constantService;

    protected BookingPaymentProcessService $bookingPaymentProcessService;

    public function __construct(
        ConstantService $constantService, 
        BookingPaymentProcessService $bookingPaymentProcessService
    ){
        $this->constantService = $constantService;
        $this->bookingPaymentProcessService = $bookingPaymentProcessService;
    }

    public function createBooking(array $bookingData, array $paymentData = [])
    {
        DB::beginTransaction();
        try {
            $listing = Listing::findOrFail($bookingData['listing_id']);

            $coupon = null;
            if (isset($bookingData['coupon_code'])) {
                $coupon = Coupon::where('code', $bookingData['coupon_code'])->firstOrFail();
            }

            $rooms = $this->bookingPaymentProcessService->normalizeRooms($bookingData['rooms'], $listing->is_entire_place);
            $addons = $this->bookingPaymentProcessService->normalizeAddons($bookingData['addons']);
            $amount = $this->calculateAmount($listing, $rooms, $addons, $coupon, $bookingData['start_date'], $bookingData['end_date']);
            $booking = $this->bookingPaymentProcessService->createBookingRecord($listing->id, $amount, $bookingData['message'] ?? null, $bookingData['start_date'], $bookingData['end_date'], $coupon->id ?? null);
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

    private function getRoomAmount($room, string $startDate, string $endDate): float
    {
        return $room->roomCategory->getCurrentPrice($startDate, $endDate) * $room->quantity; // Quantity is either from Booking<Model> or from the normalized <model>
    }

    private function getAddonAmount($addon): float
    {
        return $addon->price * $addon->quantity;
    }
}
