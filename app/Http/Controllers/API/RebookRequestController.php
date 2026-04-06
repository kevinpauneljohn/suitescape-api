<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Mail\RebookPaymentConfirmed;
use App\Mail\RebookRequestResponded;
use App\Mail\RebookRequestSubmitted;
use App\Models\Booking;
use App\Models\RebookRequest;
use App\Services\BookingAvailabilityService;
use App\Services\BookingCreateService;
use App\Services\BookingPaymentProcessService;
use App\Services\BookingRefundProcessService;
use App\Services\NotificationService;
use App\Services\UnavailableDateService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RebookRequestController extends Controller
{
    public function __construct(
        private BookingAvailabilityService $availabilityService,
        private BookingCreateService $bookingCreateService,
        private NotificationService $notificationService,
    ) {
        $this->middleware('auth:sanctum');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GUEST: Submit a rebook request
    // POST /bookings/{id}/rebook-request
    // ──────────────────────────────────────────────────────────────────────────
    public function submit(Request $request, string $id)
    {
        $booking = Booking::with([
            'listing.user',
            'bookingRooms.room.roomCategory',
            'bookingAddons',
            'coupon',
            'invoice',
            'user',
        ])->findOrFail($id);

        if ($booking->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($booking->status !== 'upcoming') {
            return response()->json(['message' => 'Only upcoming bookings can be rescheduled.'], 422);
        }

        // Auto-expire any overdue pending requests before checking for duplicates
        $booking->rebookRequests()
            ->pending()
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        // Block duplicate active (non-expired) pending requests
        if ($booking->rebookRequests()->active()->exists()) {
            // Tell the guest how long is left
            $active = $booking->rebookRequests()->active()->latest()->first();
            $hoursLeft = max(1, (int) ceil(now()->diffInMinutes($active->expires_at) / 60));
            return response()->json([
                'message' => "You already have a pending date-change request for this booking. Please wait for the host to respond (expires in ~{$hoursLeft}h).",
            ], 422);
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'reason'     => ['nullable', 'string', 'max:1000'],
        ]);

        $startDate = $validated['start_date'];
        $endDate   = $validated['end_date'];

        // Must be different dates
        if (
            $startDate === $booking->date_start->toDateString() &&
            $endDate   === $booking->date_end->toDateString()
        ) {
            return response()->json(['message' => 'The requested dates are the same as the current booking.'], 422);
        }

        // Check availability (exclude current booking)
        $availability = $this->availabilityService->checkAvailability(
            $booking->listing_id,
            $startDate,
            $endDate,
            $booking->bookingRooms->mapWithKeys(fn($br) => [$br->room_id => $br->quantity])->toArray(),
            $booking->id
        );

        if (! $availability['available']) {
            return response()->json(['available' => false, 'message' => $availability['message']], 409);
        }

        // Calculate new pricing (includes special rate logic)
        $newAmount = $this->bookingCreateService->calculateAmount(
            $booking->listing,
            $booking->bookingRooms,
            $booking->bookingAddons,
            $booking->coupon,
            $startDate,
            $endDate
        );

        $newTotal      = round($newAmount['total'], 2);
        $originalTotal = round($booking->amount, 2);
        $difference    = round($newTotal - $originalTotal, 2);

        $expiresAt = now()->addHours(RebookRequest::EXPIRY_HOURS);

        $rebookRequest = RebookRequest::create([
            'booking_id'           => $booking->id,
            'requested_by'         => auth()->id(),
            'requested_date_start' => $startDate,
            'requested_date_end'   => $endDate,
            'reason'               => $validated['reason'] ?? null,
            'status'               => 'pending',
            'original_amount'      => $originalTotal,
            'new_amount'           => $newTotal,
            'difference'           => $difference,
            'new_base_amount'               => round($newAmount['base'] ?? 0, 2),
            'new_guest_service_fee'         => round($newAmount['guest_service_fee'] ?? 0, 2),
            'new_vat'                       => round($newAmount['vat'] ?? 0, 2),
            'guest_service_fee_percentage'  => round($newAmount['guest_service_fee_percentage'] ?? 15, 2),
            'vat_percentage'                => round($newAmount['vat_percentage'] ?? 12, 2),
            'expires_at'                    => $expiresAt,
        ]);

        // Notify host in-app
        $this->notificationService->createNotification([
            'user_id'   => $booking->listing->user_id,
            'title'     => 'Date-Change Request',
            'message'   => "{$booking->user->firstname} {$booking->user->lastname} has requested to change their booking dates for \"{$booking->listing->name}\".",
            'type'      => 'rebook_request',
            'action_id' => $rebookRequest->id,
        ]);

        // Email host
        Mail::to($booking->listing->user->email)
            ->queue(new RebookRequestSubmitted($rebookRequest));

        return response()->json([
            'status'  => 'success',
            'message' => 'Your date-change request has been submitted. You will be notified once the host responds.',
            'rebook_request' => $this->formatRequest($rebookRequest),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HOST: List pending rebook requests for their listings
    // GET /host/rebook-requests
    // ──────────────────────────────────────────────────────────────────────────
    public function hostIndex(Request $request)
    {
        $status = $request->query('status', 'pending');

        // Auto-expire any overdue pending requests on the fly before returning the list
        if ($status === 'pending' || $status === 'all') {
            RebookRequest::whereHas('booking.listing', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->pending()
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
        }

        $requests = RebookRequest::with([
            'booking.listing',
            'booking.user',
            'requester',
        ])
        ->whereHas('booking.listing', function ($q) {
            $q->where('user_id', auth()->id());
        })
        ->when($status !== 'all', fn($q) => $q->where('status', $status))
        ->orderByDesc('created_at')
        ->get();

        return response()->json([
            'rebook_requests' => $requests->map(fn($r) => $this->formatRequest($r)),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HOST: Approve a rebook request
    // POST /host/rebook-requests/{id}/approve
    // ──────────────────────────────────────────────────────────────────────────
    public function approve(Request $request, string $id)
    {
        $rebookRequest = RebookRequest::with([
            'booking.listing.user',
            'booking.bookingRooms',
            'booking.user',
            'booking.invoice',
            'requester',
        ])->findOrFail($id);

        $booking = $rebookRequest->booking;

        // Only the host of the listing may respond
        if ($booking->listing->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($rebookRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been responded to.'], 422);
        }

        if ($rebookRequest->is_expired) {
            $rebookRequest->update(['status' => 'expired']);
            return response()->json(['message' => 'This request has expired. The guest will need to submit a new request.'], 422);
        }

        $startDate  = $rebookRequest->requested_date_start->toDateString();
        $endDate    = $rebookRequest->requested_date_end->toDateString();
        $difference = (float) $rebookRequest->difference;

        DB::beginTransaction();
        try {
            $rebookRequest->update([
                'status'       => 'approved',
                'responded_at' => now(),
            ]);

            if ($difference < 0) {
                // Price decreased → issue refund immediately, then update booking dates
                $refundAmount = (int) round(abs($difference) * 100);
                if ($booking->invoice?->payment_id && $refundAmount > 0) {
                    try {
                        app(BookingRefundProcessService::class)->refundPayment(
                            $booking->invoice->payment_id,
                            $refundAmount
                        );
                        \Log::info('Rebook approval partial refund issued', [
                            'rebook_request_id' => $rebookRequest->id,
                            'refund_amount'     => $refundAmount / 100,
                        ]);
                    } catch (\Exception $e) {
                        \Log::warning('Rebook approval refund failed', [
                            'rebook_request_id' => $rebookRequest->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                // Mark as paid (no guest action needed) and update dates immediately
                $rebookRequest->update(['payment_status' => 'paid']);
                $this->updateBookingDates($booking, $rebookRequest, $startDate, $endDate);
            } elseif ($difference == 0) {
                // No price change → mark as paid and update immediately
                $rebookRequest->update(['payment_status' => 'paid']);
                $this->updateBookingDates($booking, $rebookRequest, $startDate, $endDate);
            }
            // If difference > 0 (extra payment needed), booking dates stay unchanged
            // until guest completes payment via guestPay()

            // Notify guest in-app
            $actionMsg = $difference > 0
                ? ' Please open the app to complete the additional payment.'
                : ($difference < 0 ? " A refund of ₱" . number_format(abs($difference), 2) . " has been initiated and will arrive within 3–7 business days." : '');

            $this->notificationService->createNotification([
                'user_id'   => $booking->user_id,
                'title'     => 'Date-Change Request Approved',
                'message'   => "Your host approved your date-change request for \"{$booking->listing->name}\".{$actionMsg}",
                'type'      => 'rebook_approved',
                'action_id' => $booking->id,
            ]);

            // Email guest — RebookRequestResponded handles all approval cases
            // (its blade template branches on difference > 0 / < 0 / == 0)
            Mail::to($rebookRequest->requester->email)
                ->queue(new RebookRequestResponded($rebookRequest));

            DB::commit();

            return response()->json([
                'status'         => 'success',
                'message'        => 'Request approved successfully.',
                'requires_guest_payment' => $difference > 0,
                'additional_amount'      => $difference > 0 ? $difference : 0,
                'rebook_request' => $this->formatRequest($rebookRequest->fresh()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HOST: Reject a rebook request
    // POST /host/rebook-requests/{id}/reject
    // ──────────────────────────────────────────────────────────────────────────
    public function reject(Request $request, string $id)
    {
        $rebookRequest = RebookRequest::with([
            'booking.listing.user',
            'booking.user',
            'requester',
        ])->findOrFail($id);

        if ($rebookRequest->booking->listing->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($rebookRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been responded to.'], 422);
        }

        if ($rebookRequest->is_expired) {
            $rebookRequest->update(['status' => 'expired']);
            return response()->json(['message' => 'This request has expired. The guest will need to submit a new request.'], 422);
        }

        $validated = $request->validate([
            'host_note' => ['nullable', 'string', 'max:500'],
        ]);

        $rebookRequest->update([
            'status'       => 'rejected',
            'host_note'    => $validated['host_note'] ?? null,
            'responded_at' => now(),
        ]);

        // Notify guest in-app
        $this->notificationService->createNotification([
            'user_id'   => $rebookRequest->booking->user_id,
            'title'     => 'Date-Change Request Rejected',
            'message'   => "Your host rejected your date-change request for \"{$rebookRequest->booking->listing->name}\". Your original booking remains unchanged.",
            'type'      => 'rebook_rejected',
            'action_id' => $rebookRequest->booking_id,   // booking_id so guest lands on BookingDetails
        ]);

        // Email guest
        Mail::to($rebookRequest->requester->email)
            ->queue(new RebookRequestResponded($rebookRequest));

        return response()->json([
            'status'         => 'success',
            'message'        => 'Request rejected.',
            'rebook_request' => $this->formatRequest($rebookRequest->fresh()),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GUEST: Complete payment after host approval (when extra charge needed)
    // POST /rebook-requests/{id}/pay
    // ──────────────────────────────────────────────────────────────────────────
    public function guestPay(Request $request, string $id)
    {
        $rebookRequest = RebookRequest::with([
            'booking.listing.user',
            'booking.bookingRooms',
            'booking.user',
            'booking.invoice',
            'requester',
        ])->findOrFail($id);

        $booking = $rebookRequest->booking;

        if ($booking->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($rebookRequest->status !== 'approved') {
            return response()->json(['message' => 'This request has not been approved yet.'], 422);
        }

        if ($rebookRequest->difference <= 0) {
            return response()->json(['message' => 'No payment required for this request.'], 422);
        }

        // Guard: already paid
        if ($rebookRequest->payment_status === 'paid') {
            return response()->json(['message' => 'Payment has already been completed.'], 422);
        }

        $validated = $request->validate([
            'payment_type'   => ['required', 'string'],
            'payment_method' => ['nullable', 'string'],
            'card_number'    => ['nullable', 'string'],
            'exp_month'      => ['nullable', 'integer'],
            'exp_year'       => ['nullable', 'integer'],
            'cvc'            => ['nullable', 'string'],
            'gcash_number'   => ['nullable', 'string'],
        ]);

        $additionalCentavos = (int) round($rebookRequest->difference * 100);
        $paymentType        = $validated['payment_type'];

        $paymentProcessService = app(BookingPaymentProcessService::class);

        DB::beginTransaction();
        try {
            // ── Credit / Debit Card ─────────────────────────────────────────
            if ($paymentType === 'credit/debit_card') {
                $user = auth()->user();

                // Build billing details from authenticated user's profile
                $billingDetails = [
                    'name'  => trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')),
                    'email' => $user->email,
                    'phone' => $user->phone_number ?? '',
                ];

                $paymentData = array_merge($validated, [
                    'amount'          => $additionalCentavos,
                    'billing_details' => $billingDetails,
                    'billing_address' => [],
                    'description'     => 'Rebook difference payment for Booking ID: ' . $booking->id . ' - ' . ($booking->listing->name ?? 'Unknown'),
                ]);

                $result = $paymentProcessService->createBookingPayment($paymentData, $booking->id);

                if ($result['status'] === 'error') {
                    DB::rollBack();
                    return response()->json(['status' => 'error', 'message' => $result['message'] ?? 'Payment failed.'], 400);
                }

                $startDate = $rebookRequest->requested_date_start->toDateString();
                $endDate   = $rebookRequest->requested_date_end->toDateString();

                // Extract the PayMongo payment ID from the attached intent so we can
                // refund this charge separately if the booking is later cancelled.
                $rebookPaymentId = $result['data']['data']['attributes']['payments'][0]['id']
                    ?? $result['data']['data']['attributes']['payments'][0]['data']['id']
                    ?? null;

                $rebookRequest->update([
                    'payment_status'    => 'paid',
                    'rebook_payment_id' => $rebookPaymentId,
                ]);
                $this->updateBookingDates($booking, $rebookRequest, $startDate, $endDate);

                // Notify guest
                $this->notificationService->createNotification([
                    'user_id'   => $booking->user_id,
                    'title'     => 'Booking Dates Updated!',
                    'message'   => "Your additional payment was successful. Your booking for \"{$booking->listing->name}\" has been updated to the new dates.",
                    'type'      => 'rebook_paid',
                    'action_id' => $booking->id,   // booking_id so guest lands on BookingDetails
                ]);

                // Email guest confirmation
                Mail::to($rebookRequest->requester->email)
                    ->queue(new RebookPaymentConfirmed($rebookRequest->fresh()));

                DB::commit();
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Payment successful. Your booking dates have been updated.',
                    'booking' => new BookingResource($booking->fresh()),
                ]);
            }

            // ── GCash / GrabPay ─────────────────────────────────────────────
            if (in_array($paymentType, ['gcash', 'grabpay'])) {
                $sourceType = $paymentType === 'grabpay' ? 'grab_pay' : 'gcash';

                // Create a PayMongo source directly — the webhook will handle charging
                $sourceResult = $paymentProcessService->createBookingSource(
                    $sourceType,
                    $additionalCentavos,
                    $booking->id
                );

                if ($sourceResult['status'] === 'error') {
                    DB::rollBack();
                    return response()->json(['status' => 'error', 'message' => $sourceResult['message'] ?? 'Failed to create payment source.'], 400);
                }

                $sourceId    = $sourceResult['data']['id'] ?? null;
                $checkoutUrl = $sourceResult['data']['attributes']['redirect']['checkout_url'] ?? null;

                if (! $sourceId || ! $checkoutUrl) {
                    DB::rollBack();
                    return response()->json(['status' => 'error', 'message' => 'Failed to get payment redirect URL.'], 400);
                }

                // Store source_id so the webhook can find and complete this rebook
                $rebookRequest->update([
                    'epayment_source_id' => $sourceId,
                    'payment_status'     => 'pending',
                ]);

                DB::commit();
                return response()->json([
                    'status'            => 'success',
                    'requires_redirect' => true,
                    'checkout_url'      => $checkoutUrl,
                    'source_id'         => $sourceId,
                    'payment_type_params' => $sourceType,
                    'rebook_request_id' => $rebookRequest->id,
                ]);
            }

            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Invalid payment type.'], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GUEST: Get rebook requests for a specific booking
    // GET /bookings/{id}/rebook-requests
    // ──────────────────────────────────────────────────────────────────────────
    public function bookingRequests(string $id)
    {
        $booking = Booking::findOrFail($id);

        if ($booking->user_id !== auth()->id()) {
            // Also allow the host to view
            if (! $booking->listing()->where('user_id', auth()->id())->exists()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $requests = $booking->rebookRequests()
            ->with('requester')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'rebook_requests' => $requests->map(fn($r) => $this->formatRequest($r)),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GUEST: Verify / complete an e-payment rebook after WebView redirect
    // POST /rebook-requests/{id}/verify-payment
    // Called by the app after the GCash/GrabPay WebView detects success.
    // Handles local dev where PayMongo webhooks can't reach the server.
    // ──────────────────────────────────────────────────────────────────────────
    public function verifyPayment(string $id)
    {
        $rebookRequest = RebookRequest::with([
            'booking.listing',
            'booking.listing.rooms',
            'booking.user',
            'requester',
        ])->findOrFail($id);

        $booking = $rebookRequest->booking;

        if ($booking->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Already paid — just return current state
        if ($rebookRequest->payment_status === 'paid') {
            return response()->json([
                'status'         => 'already_paid',
                'message'        => 'Payment already completed.',
                'rebook_request' => $this->formatRequest($rebookRequest->fresh()),
            ]);
        }

        $sourceId = $rebookRequest->epayment_source_id;
        if (! $sourceId) {
            return response()->json(['message' => 'No payment source found for this request.'], 422);
        }

        // Query PayMongo for the source status
        try {
            $client       = new \GuzzleHttp\Client();
            $paymongoUrl  = config('paymongo.paymongo_api_url');
            $secretKey    = config('paymongo.paymongo_secret_key');

            $sourceRes  = $client->get("{$paymongoUrl}/sources/{$sourceId}", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($secretKey),
                    'Accept'        => 'application/json',
                ],
            ]);
            $sourceData   = json_decode($sourceRes->getBody()->getContents(), true);
            $sourceStatus = $sourceData['data']['attributes']['status'] ?? null;
            $sourceType   = $sourceData['data']['attributes']['type']   ?? null;
            $amount       = $sourceData['data']['attributes']['amount'] ?? null;
        } catch (\Exception $e) {
            \Log::error('Rebook verifyPayment: source fetch failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not verify payment status. Please try again.'], 500);
        }

        if ($sourceStatus !== 'chargeable') {
            return response()->json([
                'status'  => 'pending',
                'message' => 'Payment not yet confirmed by PayMongo (status: ' . $sourceStatus . ').',
            ]);
        }

        // Charge the source
        DB::beginTransaction();
        try {
            $payResponse = $client->post("{$paymongoUrl}/payments", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($secretKey),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'amount'      => $amount,
                            'currency'    => 'PHP',
                            'description' => 'Rebook difference payment for Booking ID: ' . $booking->id . ' - ' . ($booking->listing->name ?? 'Unknown'),
                            'source'      => ['id' => $sourceId, 'type' => 'source'],
                        ],
                    ],
                ],
            ]);

            $payBody    = json_decode($payResponse->getBody()->getContents(), true);
            $payStatus  = $payResponse->getStatusCode();

            if (! in_array($payStatus, [200, 201]) || isset($payBody['errors'])) {
                DB::rollBack();
                \Log::error('Rebook verifyPayment: charge failed', ['response' => $payBody]);
                return response()->json(['message' => 'Payment charge failed. Please contact support.'], 400);
            }

            $startDate = $rebookRequest->requested_date_start->toDateString();
            $endDate   = $rebookRequest->requested_date_end->toDateString();

            $rebookRequest->update([
                'payment_status'    => 'paid',
                'rebook_payment_id' => $payBody['data']['id'] ?? null,
            ]);
            $this->updateBookingDates($booking, $rebookRequest, $startDate, $endDate);

            // Notify guest
            $this->notificationService->createNotification([
                'user_id'   => $booking->user_id,
                'title'     => 'Booking Dates Updated!',
                'message'   => "Your additional payment was successful. Your booking for \"{$booking->listing->name}\" has been updated to the new dates.",
                'type'      => 'rebook_paid',
                'action_id' => $booking->id,
            ]);

            // Email guest confirmation
            Mail::to($rebookRequest->requester->email)
                ->queue(new RebookPaymentConfirmed($rebookRequest->fresh()));

            DB::commit();

            \Log::info('Rebook verifyPayment: completed', [
                'rebook_request_id' => $rebookRequest->id,
                'booking_id'        => $booking->id,
                'new_dates'         => "$startDate → $endDate",
            ]);

            return response()->json([
                'status'         => 'paid',
                'message'        => 'Payment successful. Your booking dates have been updated.',
                'rebook_request' => $this->formatRequest($rebookRequest->fresh()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Rebook verifyPayment exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Internal helper: update booking dates & unavailable date records
    // ──────────────────────────────────────────────────────────────────────────
    private function updateBookingDates(Booking $booking, RebookRequest $rebookRequest, string $startDate, string $endDate): void
    {
        $newStatus = Carbon::today()->betweenIncluded($startDate, $endDate) ? 'ongoing' : 'upcoming';

        $booking->update([
            'date_start'        => $startDate,
            'date_end'          => $endDate,
            'amount'            => $rebookRequest->new_amount,
            'base_amount'       => $rebookRequest->new_base_amount,
            'guest_service_fee' => $rebookRequest->new_guest_service_fee,
            'vat'               => $rebookRequest->new_vat,
            'status'            => $newStatus,
        ]);

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
    }

    private function formatRequest(RebookRequest $r): array
    {
        return [
            'id'                    => $r->id,
            'booking_id'            => $r->booking_id,
            'status'                => $r->status,
            'payment_status'        => $r->payment_status,
            'requested_date_start'  => $r->requested_date_start?->toDateString(),
            'requested_date_end'    => $r->requested_date_end?->toDateString(),
            'requested_nights'      => $r->requested_nights,
            'reason'                => $r->reason,
            'host_note'             => $r->host_note,
            'original_amount'       => (float) $r->original_amount,
            'new_amount'            => (float) $r->new_amount,
            'difference'            => (float) $r->difference,
            'new_base_amount'               => (float) $r->new_base_amount,
            'new_guest_service_fee'         => (float) $r->new_guest_service_fee,
            'new_vat'                       => (float) $r->new_vat,
            'guest_service_fee_percentage'  => (float) ($r->guest_service_fee_percentage ?? 15),
            'vat_percentage'                => (float) ($r->vat_percentage ?? 12),
            // Expiry fields
            'expires_at'            => $r->expires_at?->toISOString(),
            'is_expired'            => $r->is_expired,
            'seconds_until_expiry'  => $r->seconds_until_expiry,
            'responded_at'          => $r->responded_at?->toISOString(),
            'created_at'            => $r->created_at?->toISOString(),
            'requester'             => $r->requester ? [
                'id'               => $r->requester->id,
                'fullname'         => $r->requester->full_name,
                'email'            => $r->requester->email,
                'profile_image_url'=> $r->requester->profile_image_url,
            ] : null,
            'booking' => $r->booking ? [
                'id'        => $r->booking->id,
                'date_start'=> $r->booking->date_start?->toDateString(),
                'date_end'  => $r->booking->date_end?->toDateString(),
                'amount'    => (float) $r->booking->amount,
                'listing'   => $r->booking->listing ? [
                    'id'   => $r->booking->listing->id,
                    'name' => $r->booking->listing->name,
                ] : null,
                'user'      => $r->booking->user ? [
                    'id'       => $r->booking->user->id,
                    'fullname' => $r->booking->user->full_name,
                ] : null,
            ] : null,
        ];
    }
}
