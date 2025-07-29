<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Requests\DateRangeRequest;
use App\Http\Requests\FutureDateRangeRequest;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingCreateService;
use App\Services\BookingRetrievalService;
use App\Services\BookingUpdateService;
use App\Services\MailService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function __construct(
        BookingRetrievalService $bookingRetrievalService, 
        BookingCreateService $bookingCreateService, 
        BookingUpdateService $bookingUpdateService, 
        MailService $mailService,
        BookingPaymentService $bookingPaymentService
    ){
        $this->middleware('auth:sanctum');

        $this->bookingRetrievalService = $bookingRetrievalService;
        $this->bookingCreateService = $bookingCreateService;
        $this->bookingUpdateService = $bookingUpdateService;
        $this->mailService = $mailService;
        $this->bookingPaymentService = $bookingPaymentService;
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

    private function logError(string $title, string $status, $message, int $code, array $context = [])
    {
        \Log::error($title, array_merge([
            'status' => $status,
            'message' => $message,
            'code' => $code,
        ], $context));
    }
}
