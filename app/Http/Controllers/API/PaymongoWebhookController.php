<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Mail\RebookPaymentConfirmed;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\BookingCancellation;
use App\Models\RebookRequest;
use App\Services\BookingPaymentProcessService;
use App\Services\BookingRefundProcessService;
use App\Services\NotificationService;
use App\Services\UnavailableDateService;
use App\Services\MailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
class PaymongoWebhookController extends Controller
{
    protected BookingPaymentProcessService $bookingPaymentProcessService;

    private MailService $mailService;

    private UnavailableDateService $unavailableDateService;

    private NotificationService $notificationService;

    public function __construct(
        BookingPaymentProcessService $bookingPaymentProcessService,
        MailService $mailService,
        UnavailableDateService $unavailableDateService,
        NotificationService $notificationService
    ){
        $this->middleware('auth:sanctum')->except(['paymentSuccessStatus', 'paymentFailedStatus', 'ePaymentStatusCheck', 'gcashAndGrabWebhook', 'refundPaymentWebhook']);
        $this->bookingPaymentProcessService = $bookingPaymentProcessService;
        $this->mailService = $mailService;
        $this->unavailableDateService = $unavailableDateService;
        $this->notificationService = $notificationService;
    }

    public function paymentSuccessStatus(Request $request)
    {
        $booking = Booking::with('invoice')->findOrFail($request->booking_id);

        return view('payment.success', [
            'booking' => $booking,
            'invoice' => $booking->invoice,
        ]);
    }

    public function paymentFailedStatus(Request $request)
    {
        $booking = Booking::with('invoice')->findOrFail($request->booking_id);

        return view('payment.failed', [
            'booking' => $booking,
        ]);
    }

    public function gcashAndGrabWebhook(Request $request)
    {
        \Log::info('Gcash and Grab Webhook', [
            'request_data' => $request->all(),
        ]);
        $eventType = $request->input('data.attributes.type');
        $sourceId = null;
        if ($eventType === 'source.chargeable') {
            $type   = $request->input('data.attributes.data.attributes.type');
            $amount = $request->input('data.attributes.data.attributes.amount');
            $sourceId = $request->input('data.attributes.data.id');

            // ── Check if this is a rebook-difference payment ──────────────
            $rebookRequest = RebookRequest::with([
                'booking.listing',
                'booking.user',
            ])->where('epayment_source_id', $sourceId)->first();

            if ($rebookRequest) {
                return $this->handleRebookEPaymentChargeable($rebookRequest, $type, $amount, $sourceId);
            }

            // ── Regular booking payment ───────────────────────────────────
            $charge = $this->bookingPaymentProcessService->ePaymentChargeable($type, $amount, $sourceId);
            if ($charge['status'] === 'success') {
                $this->bookingPaymentProcessService->updateBookingPaymentData($sourceId, $type, 'success');
                return response()->json(['message' => 'Payment source charged successfully', 'status' => 'success'], 200);
            } else {
                return response()->json(['message' => 'Payment source charge failed', 'status' => 'error'], 400);
            }
        }

        return response()->json(['message' => 'Unhandled event type: ' . $eventType, 'status' => 'error'], 400);
    }

    /**
     * Handle GCash / GrabPay chargeable webhook for a rebook-difference payment.
     */
    private function handleRebookEPaymentChargeable(RebookRequest $rebookRequest, string $type, int $amount, string $sourceId): \Illuminate\Http\JsonResponse
    {
        // Guard: already processed
        if ($rebookRequest->payment_status === 'paid') {
            \Log::info('Rebook epayment already paid, skipping', ['rebook_request_id' => $rebookRequest->id]);
            return response()->json(['message' => 'Already paid', 'status' => 'success'], 200);
        }

        DB::beginTransaction();
        try {
            // Directly charge the PayMongo source via the Payments API.
            // We cannot reuse ePaymentChargeable() here because it looks up the source
            // by invoice.reference_number — rebook payments have no booking invoice entry.
            $payClient = new \GuzzleHttp\Client();
            $paymongoUrl       = config('paymongo.paymongo_api_url');
            $paymongoSecretKey = config('paymongo.paymongo_secret_key');

            $payResponse = $payClient->request('POST', "{$paymongoUrl}/payments", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($paymongoSecretKey),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'amount'      => $amount,
                            'currency'    => 'PHP',
                            'description' => 'Rebook difference payment for Booking ID: ' . $rebookRequest->booking_id . ' - ' . ($rebookRequest->booking->listing->name ?? 'Unknown'),
                            'source'      => ['id' => $sourceId, 'type' => 'source'],
                        ],
                    ],
                ],
            ]);

            $statusCode  = $payResponse->getStatusCode();
            $payBody     = json_decode($payResponse->getBody()->getContents(), true);

            if (! in_array($statusCode, [200, 201]) || isset($payBody['errors'])) {
                DB::rollBack();
                \Log::error('Rebook epayment charge failed', ['source_id' => $sourceId, 'response' => $payBody]);
                return response()->json(['message' => 'Charge failed', 'status' => 'error'], 400);
            }

            // Mark rebook request as paid and update booking dates
            $startDate = $rebookRequest->requested_date_start->toDateString();
            $endDate   = $rebookRequest->requested_date_end->toDateString();

            $rebookRequest->update([
                'payment_status'    => 'paid',
                'rebook_payment_id' => $payBody['data']['id'] ?? null,
            ]);

            $booking   = $rebookRequest->booking;
            $newStatus = \Illuminate\Support\Carbon::today()->betweenIncluded($startDate, $endDate) ? 'ongoing' : 'upcoming';

            $booking->update([
                'date_start'        => $startDate,
                'date_end'          => $endDate,
                'amount'            => $rebookRequest->new_amount,
                'base_amount'       => $rebookRequest->new_base_amount,
                'guest_service_fee' => $rebookRequest->new_guest_service_fee,
                'vat'               => $rebookRequest->new_vat,
                'status'            => $newStatus,
            ]);

            // Update unavailable dates
            $this->unavailableDateService->removeUnavailableDatesForBooking($booking);
            if ($booking->listing->is_entire_place) {
                $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'listing', $booking->listing->id, $startDate, $endDate);
            } else {
                foreach ($booking->rooms as $room) {
                    $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'room', $room->id, $startDate, $endDate);
                }
            }

            // Notify guest
            $this->notificationService->createNotification([
                'user_id'   => $booking->user_id,
                'title'     => 'Booking Dates Updated!',
                'message'   => "Your additional payment was received. Your booking for \"{$booking->listing->name}\" has been updated to the new dates.",
                'type'      => 'rebook_paid',
                'action_id' => $booking->id,   // booking_id so guest lands on BookingDetails
            ]);

            // Email guest confirmation
            Mail::to($rebookRequest->requester->email ?? $booking->user->email)
                ->queue(new RebookPaymentConfirmed($rebookRequest->fresh()));

            DB::commit();

            \Log::info('Rebook epayment processed successfully', [
                'rebook_request_id' => $rebookRequest->id,
                'booking_id'        => $booking->id,
            ]);

            return response()->json(['message' => 'Rebook payment charged and dates updated', 'status' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Rebook epayment webhook exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage(), 'status' => 'error'], 500);
        }
    }

    public function refundPaymentWebhook(Request $request)
    {
        $eventType    = $request->input('data.attributes.type');
        $refundStatus = $request->input('data.attributes.data.attributes.status');
        $referenceId  = $request->input('data.attributes.data.id');
        $amount       = $request->input('data.attributes.data.attributes.amount');
        $paymentId    = $request->input('data.attributes.data.attributes.payment_id');

        if ($refundStatus === 'succeeded') {
            $bookingCancellation = BookingCancellation::where('refund_id', $referenceId)->first();

            // If no BookingCancellation exists for this refund ID, it belongs to a rebook
            // additional payment (which is refunded separately and has no cancellation record).
            // Simply acknowledge and return — no invoice/booking state update needed.
            if (! $bookingCancellation) {
                \Log::info('Rebook additional payment refund webhook received — no action needed', [
                    'payment_id' => $paymentId,
                    'refund_id'  => $referenceId,
                    'amount'     => $amount,
                ]);
                return response()->json(['message' => 'Rebook refund webhook handled', 'status' => 'success'], 200);
            }

            $bookingCancellation->update([
                'status'      => 'succeeded',
                'refunded_at' => now(),
            ]);

            $bookingId = $bookingCancellation->booking_id ?? null;
            if ($bookingId) {
                $invoice = Invoice::where('booking_id', $bookingId)->first();
                if ($invoice) {
                    // Compare the webhook refund amount against the amount we requested
                    // (stored in BookingCancellation.amount, in pesos → convert to centavos).
                    // This correctly handles partial refunds due to cancellation fees:
                    // if the refunded amount matches what we asked for, it's "fully_refunded"
                    // from the booking's perspective even if PayMongo shows it as partial
                    // (because the cancellation fee was deducted from the original charge).
                    $expectedRefundCentavos = (int) round($bookingCancellation->amount * 100);
                    $isFullyRefunded = $amount >= $expectedRefundCentavos;

                    $paymentStatus = $isFullyRefunded ? 'fully_refunded' : 'partially_refunded';

                    \Log::info('Refund webhook status determination', [
                        'booking_id'              => $bookingId,
                        'webhook_amount'          => $amount,
                        'expected_refund_centavos'=> $expectedRefundCentavos,
                        'payment_status'          => $paymentStatus,
                    ]);

                    $invoice->payment_status = $paymentStatus;
                    if ($invoice->save()) {
                        $booking = $invoice->booking;
                        $booking->status = 'cancelled';
                        if ($booking->save()) {
                            $this->mailService->sendBookingCancelledEmails($booking);
                            $this->unavailableDateService->removeUnavailableDatesForBooking($booking);
                        }
                    }
                }
            }

            return response()->json([
                'message' => 'Refund webhook processed',
                'status' => 'success',
                'code' => 200,
            ], 200);
        }
    }

    public function ePaymentStatusCheck(Request $request)
    {
        $eventType = $request->input('data.attributes.type');
        $sourceId = null;
        if ($eventType === 'source.chargeable') {
            $type = $request->input('data.attributes.data.attributes.type');
            $amount = $request->input('data.attributes.data.attributes.amount');
            $sourceId = $request->input('data.attributes.data.id');
            $charge = $this->bookingPaymentProcessService->ePaymentChargeable($type, $amount, $sourceId);
            if ($charge['status'] === 'success') {
                $this->bookingPaymentProcessService->updateBookingPaymentData($sourceId, $type, 'success');
                $message = 'Payment source charged successfully';
                $status = 'success';
                $code = 200;
            } else {
                $message = 'Payment source charge failed';
                $status = 'error';
                $code = 400;
            }
        } else {
            $message = 'Unhandled event type: ' . $eventType;
            $status = 'error';
            $code = 400;
        }

        if ($eventType === 'payment.paid') {
            $type = $request->input('data.attributes.data.attributes.source.type');
            $amount = $request->input('data.attributes.data.attributes.amount');
            $sourceId = $request->input('data.attributes.data.attributes.source.id');
            if ($type === 'card') {
                $message = 'Card payment already handled in-app';
                $status = 'ignored';
                $code = 200;
            }

            $charge = $this->bookingPaymentProcessService->ePaymentChargeable($type, $amount, $sourceId);
            if ($charge['status'] === 'success') {
                $this->bookingPaymentProcessService->updateBookingPaymentData($sourceId, $type, 'success');
                $message = 'Payment source charged successfully';
                $status = 'success';
                $code = 200;
            } else {
                $message = 'Payment source charge failed';
                $status = 'error';
                $code = 400;
            }
        }

        if ($eventType === 'payment.failed') {
            $sourceId = $request->input('data.attributes.data.id');
            $this->bookingPaymentProcessService->handleEPaymentFailed($sourceId);
            $message = 'Payment failed, source handled';
            $status = 'error';
            $code = 200;
        }

        if (in_array($eventType, ['payment.refunded', 'payment.refund.updated'])) {
            $paymentId = $request->input('data.attributes.data.attributes.id');
            $refundStatus = $request->input('data.attributes.data.attributes.status'); // e.g. 'partially_refunded' or 'refunded'
            $refundId = $request->input('data.attributes.data.id');

            $invoice = Invoice::where('payment_id', $paymentId)->first();
            if ($invoice) {
                $invoice->refund_status = $refundStatus;
                $invoice->save();

                Log::info('Invoice refund updated', [
                    'payment_id' => $paymentId,
                    'refund_status' => $refundStatus
                ]);
            }

            $cancellation = BookingCancellation::where('refund_id', $refundId)->first();
            if ($cancellation) {
                $cancellation->update([
                    'status' => $refundStatus === 'refunded' ? 'succeeded' : $refundStatus,
                    'refunded_at' => now(),
                ]);

                Log::info('Booking cancellation refund updated', [
                    'refund_id' => $refundId,
                    'status' => $refundStatus
                ]);
            }

            $message = 'Refund webhook processed';
            $status = 'success';
            $code = 200;
        }
        
        if ($status === 'success') {
            if ($sourceId) {
                $invoice = Invoice::where('reference_number', $sourceId)->first();
                if ($invoice && $invoice->payment_method != 'card') {
                    $booking = Booking::find($invoice->booking_id);
                    if ($booking) {
                        $this->mailService->sendBookingCompletedEmails($booking);
                    }
                }
            }
        }

        return response()->json([
            'message' => $message,
            'status' => $status,
            'code' => $code,
        ], $code);
    }
}
 