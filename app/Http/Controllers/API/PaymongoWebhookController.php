<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\BookingCancellation;
use App\Services\BookingPaymentProcessService;
use App\Services\MailService;
use App\Services\UnavailableDateService;
class PaymongoWebhookController extends Controller
{
    protected BookingPaymentProcessService $bookingPaymentProcessService;

    private MailService $mailService;

    private UnavailableDateService $unavailableDateService;

    public function __construct(
        BookingPaymentProcessService $bookingPaymentProcessService,
        MailService $mailService,
        UnavailableDateService $unavailableDateService
    ){
        $this->middleware('auth:sanctum')->except(['paymentSuccessStatus', 'paymentFailedStatus', 'ePaymentStatusCheck', 'gcashAndGrabWebhook', 'refundPaymentWebhook']);
        $this->bookingPaymentProcessService = $bookingPaymentProcessService;
        $this->mailService = $mailService;
        $this->unavailableDateService = $unavailableDateService;
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
            $type = $request->input('data.attributes.data.attributes.type');
            $amount = $request->input('data.attributes.data.attributes.amount');
            $sourceId = $request->input('data.attributes.data.id');
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

    public function refundPaymentWebhook(Request $request)
    {
        $eventType = $request->input('data.attributes.type');
        $refundStatus = $request->input('data.attributes.data.attributes.status');
        $referenceId = $request->input('data.attributes.data.id');
        $amount = $request->input('data.attributes.data.attributes.amount');
        if ($refundStatus === 'succeeded') {
            $bookingCancellation = BookingCancellation::where('refund_id', $referenceId)->first();
            $bookingCancellation->update([
                'status' => 'succeeded',
                'refunded_at' => now(),
            ]);

            $bookingId = $bookingCancellation->booking_id ?? null;
            if ($bookingId) {
                $invoice = Invoice::where('booking_id', $bookingId)->first();
                if ($invoice) {
                    //convert to centavos
                    $bookingAmount = $invoice->booking->amount * 100;
                    $paymentStatus = 'paid';
                    if ($bookingAmount > $amount) {
                        \Log::info('Partial refund detected', [
                            'booking_id' => $bookingId,
                            'amount' => $amount,
                            'invoice_amount' => $bookingAmount,
                        ]);
                        $paymentStatus = 'partially_refunded';
                    } elseif ($bookingAmount === $amount) {
                        \Log::info('Full refund detected', [
                            'booking_id' => $bookingId,
                            'amount' => $amount,
                            'invoice_amount' => $bookingAmount,
                        ]);
                        $paymentStatus = 'fully_refunded';
                    }
                    
                    $invoice->payment_status = $paymentStatus;
                    if($invoice->save()) {
                        $booking = $invoice->booking;
                        $booking->status = 'cancelled';
                        if($booking->save()) {
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
 