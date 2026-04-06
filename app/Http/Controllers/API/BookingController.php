<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingHoldRequest;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Requests\DateRangeRequest;
use App\Http\Requests\FutureDateRangeRequest;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingAvailabilityService;
use App\Services\BookingCreateService;
use App\Services\BookingHoldService;
use App\Services\BookingRefundProcessService;
use App\Services\BookingRetrievalService;
use App\Services\BookingUpdateService;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Services\UnavailableDateService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Services\BookingPaymentService;

class BookingController extends Controller
{
    private const UPDATE_DATES_KEY = 'update_dates';

    private const MINIMUM_PAYMONGO_PRICE = 100;

    private BookingRetrievalService $bookingRetrievalService;

    private BookingCreateService $bookingCreateService;

    private BookingUpdateService $bookingUpdateService;

    private MailService $mailService;

    private BookingPaymentService $bookingPaymentService;

    private BookingHoldService $bookingHoldService;

    private BookingAvailabilityService $bookingAvailabilityService;

    public function __construct(
        BookingRetrievalService $bookingRetrievalService, 
        BookingCreateService $bookingCreateService, 
        BookingUpdateService $bookingUpdateService, 
        MailService $mailService,
        BookingPaymentService $bookingPaymentService,
        BookingHoldService $bookingHoldService,
        BookingAvailabilityService $bookingAvailabilityService
    ){
        $this->middleware('auth:sanctum');

        $this->bookingRetrievalService = $bookingRetrievalService;
        $this->bookingCreateService = $bookingCreateService;
        $this->bookingUpdateService = $bookingUpdateService;
        $this->mailService = $mailService;
        $this->bookingPaymentService = $bookingPaymentService;
        $this->bookingHoldService = $bookingHoldService;
        $this->bookingAvailabilityService = $bookingAvailabilityService;
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

    /**
     * Get Booking Amount
     *
     * Retrieves the amount for a booking.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBookingAmount(DateRangeRequest $request, string $id)
    {
        $booking = Booking::findOrFail($id);

        $amount = $this->bookingCreateService->calculateAmount(
            $booking->listing,
            $booking->bookingRooms,
            $booking->bookingAddons,
            $booking->coupon,
            $request->validated('start_date'),
            $request->validated('end_date')
        );

        return response()->json([
            'amount' => round($amount['total'], 2),
            'base_amount' => round($amount['base'], 2),
        ]);
    }

    /**
     * Create Booking
     *
     * Creates a new booking.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws Exception|Throwable
     */
    public function createBooking(CreateBookingRequest $request)
    {
        $paymentType = $request->payment_type;
        $paymentMethod = $request->payment_method;
        $totalAmount = $request->amount;
        $cardNumber = $request->card_number;
        $expMonth = $request->exp_month;
        $expYear = $request->exp_year;
        $cvc = $request->cvc;
        $gcashNumber = $request->gcash_number;
        $billingDetails = $request->billing_details;
        $billingAddress = $request->billing_address;
        $convertedAmount = (int) ($totalAmount * 100);

        $paymentData = [
            'payment_type' => $paymentType,
            'payment_method' => $paymentMethod,
            'amount' => $totalAmount,
            'card_number' => $cardNumber,
            'exp_month' => $expMonth,
            'exp_year' => $expYear,
            'cvc' => $cvc,
            'gcash_number' => $gcashNumber,
            'billing_details' => $billingDetails,
            'billing_address' => $billingAddress,
        ];

        $createBooking = $this->bookingCreateService->createBooking($request->validated(), $paymentData);
        $bookingStatus = $createBooking['status'];
        if ($createBooking['status'] === 'success') {
            $bookingData = $createBooking['booking'];
            $bookingData->message = $createBooking['message'] ?? null;
            $bookingData->booking_status = $createBooking['booking_status'] ?? null;
            $bookingData->status = $createBooking['status'] ?? 'success';
            $bookingData->code = $createBooking['code'] ?? 200;
            $bookingData->checkout_url = isset($createBooking['data']['attributes']['redirect']['checkout_url']) ? $createBooking['data']['attributes']['redirect']['checkout_url'] : null;
            $bookingData->payment_source_epayment = isset($createBooking['data']['id']) ? $createBooking['data']['id'] : null;
            $bookingData->payment_type_params = isset($createBooking['data']['attributes']['type']) ? $createBooking['data']['attributes']['type'] : null;

            if ($paymentData['payment_type'] === 'credit/debit_card') {
                $this->mailService->sendBookingCompletedEmails($bookingData);
            }
            
            return $bookingData;
        } else {
            $this->logError('Booking Creation Failed', $bookingStatus, $createBooking['message'], $createBooking['code'] ?? 500, $request->all());
            return response()->json([
                'status' => 'error',
                'message' => $createBooking['message'],
                'code' => $booking['code'] ?? 500,
            ], $createBooking['code'] ?? 500);
        }
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
        $booking = $this->bookingUpdateService->updateBookingStatus($id, $request->validated('booking_status'), $request->validated('message'));

        $this->mailService->sendBookingCancelledEmails($booking);

        return response()->json([
            'message' => 'Booking status updated successfully',
            'booking' => new BookingResource($booking),
        ]);
    }

    /**
     * Update Booking Dates
     *
     * Updates the start and end dates of a booking.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws Exception
     */
    public function updateBookingDates(FutureDateRangeRequest $request, string $id)
    {
        $booking = Booking::findOrFail($id);

        if ($booking->invoice->payment_status === 'paid') {
            $startDate = $request->validated('start_date');
            $endDate = $request->validated('end_date');

            // Check if the dates are the same or greater number of days as the original booking
            $originalDays = $booking->date_start->diffInDays($booking->date_end);
            $newDays = Carbon::parse($startDate)->diffInDays($endDate);

            if ($newDays < $originalDays) {
                return response()->json([
                    'message' => 'Cannot reduce the number of days for a paid booking',
                ], 422);
            }

            // Check if the new price is lower than the original price
            $newAmount = $this->bookingCreateService->calculateAmount(
                $booking->listing,
                $booking->bookingRooms,
                $booking->bookingAddons,
                $booking->coupon,
                $request->validated('start_date'),
                $request->validated('end_date')
            )['total'];

            // Round the new amount to 2 decimal places
            $newAmount = round($newAmount, 2);

            if ($newAmount < $booking->amount) {
                return response()->json([
                    'message' => 'Cannot reduce the price for a paid booking',
                ], 422);
            }

            // Check if there is an additional payment needed
            $amountToPay = $newAmount - $booking->amount;

            if ($amountToPay > 0) {
                // Check if the additional payment is at least the minimum amount
                if ($amountToPay < self::MINIMUM_PAYMONGO_PRICE) {
                    return response()->json([
                        'message' => 'Additional payment must be at least ₱'.self::MINIMUM_PAYMONGO_PRICE,
                    ], 422);
                }

                $additionalPayments = collect($booking->invoice->pending_additional_payments);
                $paidAddPayments = collect($booking->invoice->paid_additional_payments);

                // Check if the additional payment has been requested
                if ($additionalPayments->doesntContain(self::UPDATE_DATES_KEY) && $paidAddPayments->doesntContain(self::UPDATE_DATES_KEY)) {
                    // Update the booking invoice to have additional payment
                    $booking->invoice->update([
                        'pending_additional_payments' => $additionalPayments->push(self::UPDATE_DATES_KEY)->toArray(),
                    ]);

                    \Log::info('Additional payment requested for booking '.$booking->id.' to update dates');

                    return response()->json([
                        'message' => 'You can now update the dates for this booking!',
                    ]);
                }

                // Check if the additional payment has been paid
                if ($paidAddPayments->doesntContain(self::UPDATE_DATES_KEY)) {
                    return response()->json([
                        'message' => 'Please pay the additional amount to update the dates for this booking!',
                    ]);
                }
            }
        }

        // Update the booking dates
        $booking = $this->bookingUpdateService->updateBookingDates($id, $request->validated('start_date'), $request->validated('end_date'), self::UPDATE_DATES_KEY);

        return response()->json([
            'message' => 'Booking dates updated successfully',
            'booking' => new BookingResource($booking),
            'is_updated' => true,
        ]);
    }

    /**
     * Create a hold on booking dates.
     * Reserves dates for 15 minutes while user completes payment.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createHold(BookingHoldRequest $request)
    {
        $result = $this->bookingHoldService->createHold($request->validated());

        return response()->json($result, $result['code']);
    }

    /**
     * Confirm a held booking by processing payment.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmHold(Request $request, string $id)
    {
        $paymentData = [
            'payment_type' => $request->payment_type,
            'payment_method' => $request->payment_method,
            'amount' => $request->amount,
            'card_number' => $request->card_number,
            'exp_month' => $request->exp_month,
            'exp_year' => $request->exp_year,
            'cvc' => $request->cvc,
            'gcash_number' => $request->gcash_number,
            'billing_details' => $request->billing_details ?? [],
            'billing_address' => $request->billing_address ?? [],
            'coupon_code' => $request->coupon_code,
        ];

        $result = $this->bookingHoldService->confirmHold($id, $paymentData);

        if ($result['status'] === 'success') {
            $booking = $result['booking'] ?? null;

            if ($booking && $paymentData['payment_type'] === 'credit/debit_card') {
                $this->mailService->sendBookingCompletedEmails($booking);
            }

            // For e-payments, attach checkout URL info
            if (isset($result['data'])) {
                $result['checkout_url'] = $result['data']['attributes']['redirect']['checkout_url'] ?? null;
                $result['payment_source_epayment'] = $result['data']['id'] ?? null;
                $result['payment_type_params'] = $result['data']['attributes']['type'] ?? null;
            }
        }

        return response()->json($result, $result['code']);
    }

    /**
     * Cancel/release a booking hold.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelHold(string $id)
    {
        $result = $this->bookingHoldService->cancelHold($id);

        return response()->json($result, $result['code']);
    }

    /**
     * Check availability for a listing on specific dates.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|uuid|exists:listings,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'rooms' => 'nullable|array',
        ]);

        $rooms = $request->rooms ?? [];
        $result = $this->bookingAvailabilityService->checkAvailability(
            $request->listing_id,
            $request->start_date,
            $request->end_date,
            $rooms
        );

        return response()->json($result);
    }

    /**
     * Get availability summary for a listing.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailabilitySummary(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|uuid|exists:listings,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $result = $this->bookingAvailabilityService->getAvailabilitySummary(
            $request->listing_id,
            $request->start_date,
            $request->end_date
        );

        return response()->json($result);
    }

    /**
     * Preview rebook pricing.
     *
     * Calculates the new amount for changed dates, shows the difference,
     * and whether additional payment or a refund is needed.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function rebookPreview(FutureDateRangeRequest $request, string $id)
    {
        $booking = Booking::with(['listing', 'bookingRooms.room.roomCategory', 'bookingAddons', 'coupon', 'invoice'])->findOrFail($id);

        // Only allow rebook for upcoming bookings owned by the authenticated user
        if ($booking->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array($booking->status, ['upcoming'])) {
            return response()->json(['message' => 'Only upcoming bookings can be rebooked'], 422);
        }

        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        // Check availability for the new dates (excluding the current booking)
        $availability = $this->bookingAvailabilityService->checkAvailability(
            $booking->listing_id,
            $startDate,
            $endDate,
            $booking->bookingRooms->mapWithKeys(fn($br) => [$br->room_id => $br->quantity])->toArray(),
            $booking->id
        );

        if (!$availability['available']) {
            return response()->json([
                'available' => false,
                'message' => $availability['message'],
            ], 409);
        }

        // Calculate new amount with special rates applied
        $newAmount = $this->bookingCreateService->calculateAmount(
            $booking->listing,
            $booking->bookingRooms,
            $booking->bookingAddons,
            $booking->coupon,
            $startDate,
            $endDate
        );

        $newTotal = round($newAmount['total'], 2);
        $originalTotal = round($booking->amount, 2);
        $difference = round($newTotal - $originalTotal, 2);

        $originalNights = max(1, $booking->date_start->diffInDays($booking->date_end));
        $newNights = max(1, Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)));

        return response()->json([
            'available' => true,
            'original' => [
                'amount' => $originalTotal,
                'guest_service_fee' => (float) ($booking->guest_service_fee ?? 0),
                'vat' => (float) ($booking->vat ?? 0),
                'nights' => $originalNights,
                'date_start' => $booking->date_start->toDateString(),
                'date_end' => $booking->date_end->toDateString(),
            ],
            'new' => [
                'amount' => $newTotal,
                'subtotal' => round($newAmount['subtotal'] ?? 0, 2),
                'base_amount' => round($newAmount['base'], 2),
                'guest_service_fee' => round($newAmount['guest_service_fee'] ?? 0, 2),
                'guest_service_fee_percentage' => $newAmount['guest_service_fee_percentage'] ?? 0,
                'vat' => round($newAmount['vat'] ?? 0, 2),
                'vat_percentage' => $newAmount['vat_percentage'] ?? 0,
                'nights' => $newNights,
                'date_start' => $startDate,
                'date_end' => $endDate,
            ],
            'difference' => $difference,
            'action' => $difference > 0 ? 'additional_payment' : ($difference < 0 ? 'refund' : 'no_change'),
            'additional_payment' => $difference > 0 ? $difference : 0,
            'refund_amount' => $difference < 0 ? abs($difference) : 0,
        ]);
    }

    /**
     * Confirm a rebook (date modification) for an existing booking.
     *
     * Handles additional payment (if price increased) or partial refund (if price decreased).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmRebook(Request $request, string $id)
    {
        $booking = Booking::with(['listing', 'bookingRooms.room.roomCategory', 'bookingAddons', 'coupon', 'invoice'])->findOrFail($id);

        if ($booking->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array($booking->status, ['upcoming'])) {
            return response()->json(['message' => 'Only upcoming bookings can be rebooked'], 422);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        if (!$startDate || !$endDate) {
            return response()->json(['message' => 'start_date and end_date are required'], 422);
        }

        // Re-check availability
        $availability = $this->bookingAvailabilityService->checkAvailability(
            $booking->listing_id,
            $startDate,
            $endDate,
            $booking->bookingRooms->mapWithKeys(fn($br) => [$br->room_id => $br->quantity])->toArray(),
            $booking->id
        );

        if (!$availability['available']) {
            return response()->json([
                'status' => 'error',
                'message' => $availability['message'],
            ], 409);
        }

        // Calculate new amount
        $newAmount = $this->bookingCreateService->calculateAmount(
            $booking->listing,
            $booking->bookingRooms,
            $booking->bookingAddons,
            $booking->coupon,
            $startDate,
            $endDate
        );

        $newTotal = round($newAmount['total'], 2);
        $originalTotal = round($booking->amount, 2);
        $difference = round($newTotal - $originalTotal, 2);

        // If price increased, require payment data for the additional amount
        if ($difference > 0) {
            $paymentType = $request->payment_type;
            if (!$paymentType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Additional payment is required. Please provide payment details.',
                    'requires_payment' => true,
                    'additional_amount' => $difference,
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Handle refund if price decreased
            if ($difference < 0 && $booking->invoice?->payment_id) {
                $refundAmount = abs($difference) * 100; // Convert to centavos
                try {
                    $refundResponse = app(BookingRefundProcessService::class)->refundPayment(
                        $booking->invoice->payment_id,
                        (int) $refundAmount
                    );
                    if ($refundResponse['status'] !== 'success') {
                        \Log::warning('Rebook refund failed, continuing with date update', [
                            'booking_id' => $booking->id,
                            'refund_amount' => abs($difference),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Rebook refund exception, continuing with date update', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Handle additional payment if price increased
            $additionalPaymentResult = null;
            if ($difference > 0) {
                $paymentType = $request->payment_type;
                $additionalAmountCentavos = (int) round($difference * 100);

                $paymentData = [
                    'payment_type' => $paymentType,
                    'payment_method' => $request->payment_method ?? $paymentType,
                    'amount' => $additionalAmountCentavos,
                    'card_number' => $request->card_number,
                    'exp_month' => $request->exp_month,
                    'exp_year' => $request->exp_year,
                    'cvc' => $request->cvc,
                    'gcash_number' => $request->gcash_number,
                    'billing_details' => $request->billing_details ?? [],
                    'billing_address' => $request->billing_address ?? [],
                ];

                $paymentProcessService = app(\App\Services\BookingPaymentProcessService::class);

                if ($paymentType === 'credit/debit_card') {
                    $paymentResult = $paymentProcessService->createBookingPayment($paymentData, $booking->id);

                    if ($paymentResult['status'] === 'error') {
                        DB::rollBack();
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Additional payment failed: ' . ($paymentResult['message'] ?? 'Unknown error'),
                        ], 400);
                    }

                    // Update invoice with new payment info
                    if (isset($paymentResult['data'])) {
                        $piData = $paymentResult['data']['data']['data'] ?? [];
                        $status = $piData['attributes']['status'] ?? 'pending';
                        $paymentStatus = ($status === 'succeeded') ? 'paid' : 'pending';
                        $paymentIntentId = $piData['id'] ?? null;
                        $paymentId = $piData['attributes']['payments'][0]['id'] ?? null;

                        // Update or create invoice with new payment reference
                        if ($booking->invoice) {
                            $booking->invoice->update([
                                'payment_status' => $paymentStatus,
                                'payment_id' => $paymentId ?? $booking->invoice->payment_id,
                            ]);
                        }
                    }

                    $additionalPaymentResult = ['method' => 'card', 'status' => 'paid'];
                } elseif (in_array($paymentType, ['gcash', 'grabpay'])) {
                    $paymentResult = $paymentProcessService->createEPayment($paymentData, $booking->id);

                    if ($paymentResult['status'] === 'error') {
                        DB::rollBack();
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Additional payment failed: ' . ($paymentResult['message'] ?? 'Unknown error'),
                        ], 400);
                    }

                    // For e-payments, we need to redirect the user to complete payment
                    // Update dates optimistically — webhook will finalize
                    $additionalPaymentResult = [
                        'method' => $paymentType,
                        'status' => 'pending',
                        'checkout_url' => $paymentResult['booking_source']['data']['attributes']['redirect']['checkout_url'] ?? null,
                        'source_id' => $paymentResult['booking_source']['data']['id'] ?? null,
                        'payment_type_params' => $paymentResult['booking_source']['data']['attributes']['type'] ?? null,
                    ];
                }
            }

            // Update booking dates and amount
            $newStatus = Carbon::today()->betweenIncluded($startDate, $endDate) ? 'ongoing' : 'upcoming';
            $booking->update([
                'date_start' => $startDate,
                'date_end' => $endDate,
                'amount' => $newTotal,
                'base_amount' => round($newAmount['base'], 2),
                'guest_service_fee' => round($newAmount['guest_service_fee'] ?? 0, 2),
                'vat' => round($newAmount['vat'] ?? 0, 2),
                'status' => $newStatus,
            ]);

            // Update unavailable dates
            $unavailableDateService = app(UnavailableDateService::class);
            $unavailableDateService->removeUnavailableDatesForBooking($booking);

            if ($booking->invoice?->payment_status === 'paid') {
                if ($booking->listing->is_entire_place) {
                    $unavailableDateService->addUnavailableDatesForBooking($booking, 'listing', $booking->listing->id, $startDate, $endDate);
                } else {
                    foreach ($booking->rooms as $room) {
                        $unavailableDateService->addUnavailableDatesForBooking($booking, 'room', $room->id, $startDate, $endDate);
                    }
                }
            }

            // Notify the host
            app(NotificationService::class)->createNotification([
                'user_id' => $booking->listing->user_id,
                'title' => 'Booking Dates Modified',
                'message' => "{$booking->user->firstname} {$booking->user->lastname} has modified their booking for \"{$booking->listing->name}\" to {$startDate} - {$endDate}.",
                'type' => 'booking_rebooked',
                'action_id' => $booking->id,
            ]);

            DB::commit();

            $responseData = [
                'status' => 'success',
                'message' => 'Booking dates updated successfully',
                'booking' => new BookingResource($booking->fresh(['listing', 'bookingRooms', 'bookingAddons', 'coupon', 'invoice', 'user'])),
                'original_amount' => $originalTotal,
                'new_amount' => $newTotal,
                'difference' => $difference,
                'action' => $difference > 0 ? 'additional_payment' : ($difference < 0 ? 'refund' : 'no_change'),
            ];

            // If e-payment redirect is needed, include checkout URL
            if ($additionalPaymentResult && $additionalPaymentResult['status'] === 'pending') {
                $responseData['requires_redirect'] = true;
                $responseData['checkout_url'] = $additionalPaymentResult['checkout_url'] ?? null;
                $responseData['source_id'] = $additionalPaymentResult['source_id'] ?? null;
                $responseData['payment_type_params'] = $additionalPaymentResult['payment_type_params'] ?? null;
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update booking: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function logError(string $title, string $status, $message, int $code, array $context = [])
    {
        \Log::error($title, array_merge([
            'status' => $status,
            'message' => $message,
            'code' => $code,
        ], $context));
    }
}
