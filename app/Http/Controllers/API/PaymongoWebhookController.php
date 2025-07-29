<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\BookingPaymentProcessService;
use App\Services\MailService;
class PaymongoWebhookController extends Controller
{
    protected BookingPaymentProcessService $bookingPaymentProcessService;

    private MailService $mailService;

    public function __construct(
        BookingPaymentProcessService $bookingPaymentProcessService,
        MailService $mailService
    ){
        $this->middleware('auth:sanctum')->except(['paymentSuccessStatus', 'paymentFailedStatus', 'ePaymentStatusCheck']);
        $this->bookingPaymentProcessService = $bookingPaymentProcessService;
        $this->mailService = $mailService;
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

    public function ePaymentStatusCheck(Request $request)
    {
        \Log::info('Paymongo Webhook Request', [
            'request' => $request->all(),
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
        \Log::info('Paymongo Webhook Event', [
            'event_type' => $eventType,
            'source_id' => $sourceId,
            'message' => $message,
            'status' => $status,
            'code' => $code,
        ]);
        return response()->json([
            'message' => $message,
            'status' => $status,
            'code' => $code,
        ], $code);
    }
}
 