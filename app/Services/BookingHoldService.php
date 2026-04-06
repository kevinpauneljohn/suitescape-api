<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingHoldService
{
    protected BookingAvailabilityService $availabilityService;
    protected BookingCreateService $bookingCreateService;
    protected BookingPaymentProcessService $paymentProcessService;

    /**
     * Hold duration in minutes.
     */
    public const HOLD_DURATION_MINUTES = 15;

    public function __construct(
        BookingAvailabilityService $availabilityService,
        BookingCreateService $bookingCreateService,
        BookingPaymentProcessService $paymentProcessService
    ) {
        $this->availabilityService = $availabilityService;
        $this->bookingCreateService = $bookingCreateService;
        $this->paymentProcessService = $paymentProcessService;
    }

    /**
     * Maximum number of active holds a user can have at once.
     */
    public const MAX_ACTIVE_HOLDS_PER_USER = 3;

    /**
     * Create a hold on dates for a booking.
     * This reserves the dates for a limited time while the user completes payment.
     */
    public function createHold(array $data): array
    {
        $user = auth()->user();
        $listingId = $data['listing_id'];
        $startDate = $data['start_date'];
        $endDate = $data['end_date'];
        $rooms = $data['rooms'] ?? [];
        $idempotencyKey = $data['idempotency_key'] ?? null;

        // Rate limit: prevent users from holding too many listings simultaneously
        $activeHoldsCount = Booking::where('user_id', $user->id)
            ->activeHolds()
            ->count();

        if ($activeHoldsCount >= self::MAX_ACTIVE_HOLDS_PER_USER) {
            return [
                'status' => 'error',
                'message' => 'You have too many pending reservations. Please complete or cancel existing bookings first.',
                'code' => 429,
            ];
        }

        // Check for existing hold with same idempotency key (prevent duplicate holds)
        if ($idempotencyKey) {
            $existingHold = Booking::where('idempotency_key', $idempotencyKey)
                ->where('status', 'held')
                ->where('hold_expires_at', '>', now())
                ->first();

            if ($existingHold) {
                return [
                    'status' => 'success',
                    'message' => 'Hold already exists for this request.',
                    'hold' => $existingHold,
                    'hold_id' => $existingHold->id,
                    'expires_at' => $existingHold->hold_expires_at->toIso8601String(),
                    'remaining_seconds' => (int) now()->diffInSeconds($existingHold->hold_expires_at, false),
                    'code' => 200,
                ];
            }
        }

        // Cancel any existing active holds by this user for the same listing
        $this->cancelUserHoldsForListing($user->id, $listingId);

        // Use a transaction with pessimistic locking to prevent race conditions.
        // Two users requesting the same dates simultaneously:
        //   - First user acquires the lock, creates the hold
        //   - Second user waits for the lock, then sees the first user's hold
        DB::beginTransaction();
        try {
            // Acquire a pessimistic lock on all blocking bookings for this listing+dates.
            // This prevents two concurrent requests from both passing the availability check
            // before either has inserted their hold.
            Booking::blockingAvailability()
                ->where('listing_id', $listingId)
                ->where('date_start', '<', $endDate)
                ->where('date_end', '>', $startDate)
                ->lockForUpdate()
                ->get();

            // Now check availability INSIDE the lock — guaranteed to see latest state
            $availability = $this->availabilityService->checkAvailability(
                $listingId,
                $startDate,
                $endDate,
                $rooms
            );

            if (!$availability['available']) {
                DB::rollBack();
                return [
                    'status' => 'error',
                    'message' => $availability['message'],
                    'type' => $availability['type'],
                    'details' => $availability['details'] ?? null,
                    'unavailable_rooms' => $availability['unavailable_rooms'] ?? null,
                    'code' => 409,
                ];
            }

            $listing = Listing::with(['rooms.roomCategory'])->findOrFail($listingId);

            // Calculate amount for the hold record
            $roomsCollection = $this->paymentProcessService->normalizeRooms(
                $data['rooms'] ?? [],
                $listing->is_entire_place
            );
            $addonsCollection = $this->paymentProcessService->normalizeAddons(
                $data['addons'] ?? []
            );
            $amount = $this->bookingCreateService->calculateAmount(
                $listing,
                $roomsCollection,
                $addonsCollection,
                null,
                $startDate,
                $endDate
            );

            $holdExpiresAt = now()->addMinutes(self::HOLD_DURATION_MINUTES);

            $booking = $user->bookings()->create([
                'listing_id' => $listingId,
                'amount' => $amount['total'],
                'base_amount' => $amount['base'],
                'guest_service_fee' => $amount['guest_service_fee'] ?? 0,
                'vat' => $amount['vat'] ?? 0,
                'message' => $data['message'] ?? null,
                'date_start' => $startDate,
                'date_end' => $endDate,
                'status' => 'held',
                'hold_expires_at' => $holdExpiresAt,
                'idempotency_key' => $idempotencyKey ?? Str::uuid()->toString(),
            ]);

            // Add rooms to the hold booking so they block availability
            $this->paymentProcessService->addBookingRooms($booking, $roomsCollection);
            $this->paymentProcessService->addBookingAddons($booking, $addonsCollection);

            DB::commit();

            Log::info('Booking hold created', [
                'hold_id' => $booking->id,
                'user_id' => $user->id,
                'listing_id' => $listingId,
                'expires_at' => $holdExpiresAt->toIso8601String(),
            ]);

            return [
                'status' => 'success',
                'message' => 'Dates held successfully. Complete payment within ' . self::HOLD_DURATION_MINUTES . ' minutes.',
                'hold' => $booking,
                'hold_id' => $booking->id,
                'expires_at' => $holdExpiresAt->toIso8601String(),
                'remaining_seconds' => self::HOLD_DURATION_MINUTES * 60,
                'code' => 201,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            // Duplicate idempotency key — the previous hold was deleted but the INSERT
            // raced with itself (e.g. React Native unmount/remount). Return a clear
            // message so the frontend can retry with a new key.
            if ($e->errorInfo[1] === 1062) {
                Log::warning('Duplicate idempotency key on booking hold, likely a rapid remount', [
                    'user_id' => $user->id,
                    'listing_id' => $listingId,
                ]);
                return [
                    'status' => 'error',
                    'message' => 'A reservation was just released. Please wait a moment and try again.',
                    'code' => 409,
                ];
            }

            Log::error('Failed to create booking hold', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'listing_id' => $listingId,
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to hold dates. Please try again.',
                'code' => 500,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create booking hold', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'listing_id' => $listingId,
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to hold dates. Please try again.',
                'code' => 500,
            ];
        }
    }

    /**
     * Confirm a held booking by processing payment.
     * Converts a held booking to a confirmed booking.
     */
    public function confirmHold(string $holdId, array $paymentData): array
    {
        $user = auth()->user();

        $hold = Booking::where('id', $holdId)
            ->where('user_id', $user->id)
            ->where('status', 'held')
            ->first();

        if (!$hold) {
            return [
                'status' => 'error',
                'message' => 'Hold not found or already processed.',
                'code' => 404,
            ];
        }

        // Check if hold has expired
        if ($hold->isHoldExpired()) {
            $hold->delete();
            return [
                'status' => 'error',
                'message' => 'Your hold has expired. Please try booking again.',
                'code' => 410,
            ];
        }

        // Re-verify availability (safety check)
        $requestedRooms = [];
        foreach ($hold->bookingRooms as $bookingRoom) {
            $requestedRooms[$bookingRoom->room_id] = $bookingRoom->quantity;
        }
        $availability = $this->availabilityService->checkAvailability(
            $hold->listing_id,
            $hold->date_start->format('Y-m-d'),
            $hold->date_end->format('Y-m-d'),
            $requestedRooms,
            $hold->id
        );

        if (!$availability['available']) {
            $hold->delete();
            return [
                'status' => 'error',
                'message' => 'The dates are no longer available. Your hold has been released.',
                'code' => 409,
            ];
        }

        DB::beginTransaction();
        try {
            $paymentType = $paymentData['payment_type'] ?? null;

            // Apply coupon if provided
            if (!empty($paymentData['coupon_code'])) {
                $coupon = \App\Models\Coupon::where('code', $paymentData['coupon_code'])->first();
                if ($coupon) {
                    $hold->update(['coupon_id' => $coupon->id]);
                    $listing = $hold->listing;
                    $amount = $this->bookingCreateService->calculateAmount(
                        $listing,
                        $hold->bookingRooms,
                        $hold->bookingAddons,
                        $coupon,
                        $hold->date_start->format('Y-m-d'),
                        $hold->date_end->format('Y-m-d')
                    );
                    $hold->update([
                        'amount' => $amount['total'],
                        'base_amount' => $amount['base'],
                        'guest_service_fee' => $amount['guest_service_fee'] ?? 0,
                        'vat' => $amount['vat'] ?? 0,
                    ]);
                }
            }

            // ─── Authoritative amount override ────────────────────────────────────────
            // Always charge what the backend calculated and stored on the hold record.
            // The client-supplied amount is IGNORED to prevent any frontend/backend
            // rounding or VAT-base discrepancies from reaching PayMongo.
            $hold->refresh(); // re-read after potential coupon update
            $paymentData['amount'] = (int) round(floatval($hold->amount) * 100);
            // ─────────────────────────────────────────────────────────────────────────

            // Determine initial status based on payment type
            $initialStatus = 'to_pay';
            if (in_array($paymentType, ['gcash', 'grabpay'])) {
                $initialStatus = 'pending_payment';
            }

            // Update hold status to the payment-in-progress status
            $hold->update([
                'status' => $initialStatus,
                'hold_expires_at' => null,
            ]);

            // Process payment
            if ($paymentType === 'credit/debit_card') {
                $createPayment = $this->paymentProcessService->createBookingPayment($paymentData, $hold->id);

                if ($createPayment['status'] === 'error') {
                    $hold->update([
                        'status' => 'held',
                        'hold_expires_at' => now()->addMinutes(self::HOLD_DURATION_MINUTES),
                    ]);
                    DB::rollBack();
                    return [
                        'status' => 'error',
                        'message' => $createPayment['message'],
                        'code' => $createPayment['code'] ?? 400,
                        'hold_id' => $hold->id,
                        'hold_restored' => true,
                        'expires_at' => $hold->fresh()->hold_expires_at->toIso8601String(),
                    ];
                }

                if (isset($createPayment['data'])) {
                    $status = $createPayment['data']['data']['data']['attributes']['status'] ?? 'pending';
                    $paymentStatus = ($status === 'succeeded') ? 'paid' : 'pending';
                    $paymentIntentId = $createPayment['data']['data']['data']['id'] ?? null;
                    $paymentId = $createPayment['data']['data']['data']['attributes']['payments'][0]['id'] ?? null;

                    $hold->update(['payment_intent_id' => $paymentIntentId]);

                    $createInvoice = $this->paymentProcessService->createBookingInvoice(
                        $hold, $paymentIntentId, $paymentStatus, $paymentId
                    );

                    if (!$createInvoice) {
                        $hold->update([
                            'status' => 'held',
                            'hold_expires_at' => now()->addMinutes(self::HOLD_DURATION_MINUTES),
                        ]);
                        DB::rollBack();
                        return [
                            'status' => 'error',
                            'message' => 'Failed to create booking invoice',
                            'code' => 500,
                        ];
                    }

                    $paymentMethodValue = $this->paymentProcessService->convertPaymentMethodValue($paymentData['payment_method']);
                    $updateResult = $this->paymentProcessService->updateBookingPaymentData(
                        $paymentIntentId, $paymentMethodValue, $paymentStatus
                    );

                    if ($updateResult['status'] === 'error') {
                        $hold->update([
                            'status' => 'held',
                            'hold_expires_at' => now()->addMinutes(self::HOLD_DURATION_MINUTES),
                        ]);
                        DB::rollBack();
                        return [
                            'status' => 'error',
                            'message' => $updateResult['message'],
                            'code' => $updateResult['code'] ?? 500,
                        ];
                    }

                    $getBookingStatus = $this->paymentProcessService->getBookingStatus($paymentIntentId);
                    $bookingStatus = null;
                    $paymentMethod = null;
                    if ($getBookingStatus['status'] === 'success') {
                        $bookingStatus = $getBookingStatus['booking_status'];
                        $paymentMethod = $getBookingStatus['payment_method'];
                    } else {
                        $hold->update([
                            'status' => 'held',
                            'hold_expires_at' => now()->addMinutes(self::HOLD_DURATION_MINUTES),
                        ]);
                        DB::rollBack();
                        return [
                            'status' => 'error',
                            'message' => $getBookingStatus['message'],
                            'code' => $getBookingStatus['code'] ?? 500,
                        ];
                    }

                    DB::commit();

                    Log::info('Booking hold confirmed with card payment', [
                        'booking_id' => $hold->id,
                        'status' => $bookingStatus,
                    ]);

                    return [
                        'status' => 'success',
                        'message' => 'Booking confirmed successfully!',
                        'booking' => $hold->fresh(),
                        'booking_id' => $hold->id,
                        'booking_status' => $bookingStatus,
                        'payment_method' => $paymentMethod,
                        'code' => 200,
                    ];
                }
            } elseif (in_array($paymentType, ['gcash', 'grabpay'])) {
                $createPayment = $this->paymentProcessService->createEPayment($paymentData, $hold->id);

                if ($createPayment['status'] === 'error') {
                    $hold->update([
                        'status' => 'held',
                        'hold_expires_at' => now()->addMinutes(self::HOLD_DURATION_MINUTES),
                    ]);
                    DB::rollBack();
                    return [
                        'status' => 'error',
                        'message' => $createPayment['message'],
                        'code' => $createPayment['code'] ?? 400,
                        'hold_id' => $hold->id,
                        'hold_restored' => true,
                    ];
                }

                DB::commit();

                Log::info('Booking hold confirmed with e-payment, awaiting redirect', [
                    'booking_id' => $hold->id,
                ]);

                return [
                    'status' => $createPayment['status'],
                    'message' => 'Payment initiated. Complete payment to confirm booking.',
                    'booking' => $hold->fresh(),
                    'booking_id' => $hold->id,
                    'data' => $createPayment['booking_source'] ?? null,
                    'code' => $createPayment['code'],
                ];
            }

            DB::rollBack();
            return [
                'status' => 'error',
                'message' => 'Invalid payment type.',
                'code' => 400,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                $hold->update([
                    'status' => 'held',
                    'hold_expires_at' => now()->addMinutes(self::HOLD_DURATION_MINUTES),
                ]);
            } catch (\Exception $restoreEx) {
                Log::error('Failed to restore hold after confirm error', [
                    'hold_id' => $hold->id,
                    'error' => $restoreEx->getMessage(),
                ]);
            }

            Log::error('Failed to confirm booking hold', [
                'hold_id' => $holdId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to process payment. Your hold has been restored.',
                'code' => 500,
                'hold_id' => $hold->id,
                'hold_restored' => true,
            ];
        }
    }

    /**
     * Cancel/release a hold.
     */
    public function cancelHold(string $holdId): array
    {
        $user = auth()->user();

        $hold = Booking::where('id', $holdId)
            ->where('user_id', $user->id)
            ->where('status', 'held')
            ->first();

        if (!$hold) {
            return [
                'status' => 'error',
                'message' => 'Hold not found.',
                'code' => 404,
            ];
        }

        $hold->delete();

        Log::info('Booking hold cancelled', [
            'hold_id' => $holdId,
            'user_id' => $user->id,
        ]);

        return [
            'status' => 'success',
            'message' => 'Hold released successfully.',
            'code' => 200,
        ];
    }

    /**
     * Get the current active hold for a user on a listing (if any).
     */
    public function getActiveHold(string $userId, string $listingId): ?Booking
    {
        return Booking::where('user_id', $userId)
            ->where('listing_id', $listingId)
            ->activeHolds()
            ->first();
    }

    /**
     * Cancel all active holds by a user for a specific listing.
     */
    public function cancelUserHoldsForListing(string $userId, string $listingId): int
    {
        $holds = Booking::where('user_id', $userId)
            ->where('listing_id', $listingId)
            ->activeHolds()
            ->get();

        $count = 0;
        foreach ($holds as $hold) {
            $hold->delete();
            $count++;
        }

        return $count;
    }

    /**
     * Clean up all expired holds system-wide.
     * Called by scheduled command.
     */
    public function cleanupExpiredHolds(): int
    {
        $expiredHolds = Booking::expiredHolds()->get();
        $count = 0;

        foreach ($expiredHolds as $hold) {
            Log::info('Cleaning up expired hold', [
                'hold_id' => $hold->id,
                'user_id' => $hold->user_id,
                'listing_id' => $hold->listing_id,
                'expired_at' => $hold->hold_expires_at,
            ]);
            $hold->delete();
            $count++;
        }

        return $count;
    }
}
