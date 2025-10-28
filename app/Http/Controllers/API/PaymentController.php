<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentLinkRequest;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\CreatePaymentSourceRequest;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingCreateService;
use App\Services\BookingUpdateService;
use App\Services\PaymentService;
use App\Services\BookingPaymentService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;

use App\Http\Requests\CreateBookingRequest;

class PaymentController extends Controller
{
    private BookingCreateService $bookingCreateService;

    private BookingUpdateService $bookingUpdateService;

    private PaymentService $paymentService;

    private BookingPaymentService $bookingPaymentService;

    public function __construct(
        BookingCreateService $bookingCreateService, 
        BookingUpdateService $bookingUpdateService, 
        PaymentService $paymentService, 
        BookingPaymentService $bookingPaymentService
    )
    {
        $this->middleware('auth:sanctum')->except(['linkPaymentPaid', 'sourceChargeable', 'paymentSuccessStatus', 'paymentFailedStatus', 'paymentStatusCheck']);

        $this->bookingCreateService = $bookingCreateService;
        $this->bookingUpdateService = $bookingUpdateService;
        $this->paymentService = $paymentService;
        $this->bookingPaymentService = $bookingPaymentService;

        $this->bookingCreateService = $bookingCreateService;
    }

    /**
     * Create Payment Link
     *
     * Creates a new payment link based on the provided details.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws GuzzleException
     */
    public function createPaymentLink(CreatePaymentLinkRequest $request)
    {
        $booking = Booking::findOrFail($request->validated('booking_id'));
        $invoice = $booking->invoice;

        try {
            // Check if payment link already exists and no additional payments are pending
            if (optional($invoice)->reference_number && collect(optional($invoice->pending_additional_payments))->isEmpty()) {
                $paymentLink = $this->paymentService->getPaymentLink($booking->invoice->reference_number);

                return response()->json([
                    'message' => 'Payment link already exists',
                    'payment_link' => $paymentLink,
                    'invoice' => $invoice,
                ]);
            }

            // Archive existing payment link
            //            if (optional($invoice)->reference_number) {
            //                $this->paymentService->archivePaymentLink($invoice->reference_number);
            //            }

            // Create payment link
            $paymentLink = $this->paymentService->createPaymentLink(
                $request->validated('amount'),
                $request->validated('description')
            );
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        if ($booking->invoice()->exists()) {
            $invoice = $this->bookingUpdateService->updateBookingInvoice($booking->id, [
                'reference_number' => $paymentLink['id'],
            ]);
        } else {
            $invoice = $this->bookingCreateService->createBookingInvoice($booking, $paymentLink['id']);
        }

        return response()->json([
            'message' => 'Payment link created',
            'payment_link' => $paymentLink,
            'invoice' => $invoice,
        ]);
    }

    /**
     * Create Payment Source
     *
     * Creates a new payment source based on the provided amount.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPaymentSource(CreatePaymentSourceRequest $request)
    {
        $booking = Booking::findOrFail($request->validated('booking_id'));

        try {
            // Check if payment source already exists
            if ($booking->invoice()->exists()) {
                $paymentSource = $this->paymentService->getPaymentSource($booking->invoice->reference_number);

                return response()->json([
                    'message' => 'Payment source already exists',
                    'payment_source' => $paymentSource,
                    'invoice' => $booking->invoice,
                ], 409);
            }

            // Create payment source
            $paymentSource = $this->paymentService->createPaymentSource(
                $request->validated('type'),
                $request->validated('amount'),
                $request->validated('booking_id'),
            );
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        // Create invoice
        $invoice = $this->bookingCreateService->createBookingInvoice($booking, $paymentSource['id']);

        return response()->json([
            'message' => 'Payment source created',
            'payment_source' => $paymentSource,
            'invoice' => $invoice,
        ]);
    }

    /**
     * Create Payment
     *
     * Creates a new payment based on the provided details.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws GuzzleException
     */
    public function createPayment(CreatePaymentRequest $request)
    {
        $booking = Booking::findOrFail($request->validated('booking_id'));

        try {
            // Check if payment already exists
            if ($booking->invoice()->exists()) {
                $paymentIntent = $this->paymentService->getPaymentIntent($booking->invoice->reference_number);

                return response()->json([
                    'message' => 'Payment already exists',
                    'payment_intent' => $paymentIntent,
                    'invoice' => $booking->invoice,
                ], 409);
            }

            // Create payment
            $payment = $this->paymentService->createPayment($request->validated());
        } catch (Exception $e) {
            // return response()->json([
            //     'is_paymongo_error' => true,
            //     'message' => $e->getMessage(),
            // ], $e->getCode());
            $statusCode = $e->getCode();
            if ($statusCode < 100 || $statusCode > 599) {
                $statusCode = 500;
            }

            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $statusCode);
        }

        // Create invoice
        // $invoice = $this->bookingCreateService->createBookingInvoice($request->validated('booking_id'), $payment['id']);
        $invoice = $this->bookingCreateService->createBookingInvoice($booking, $payment['id']);

        return response()->json([
            'message' => 'Payment successful',
            'payment' => $payment,
            'invoice' => $invoice,
        ]);
    }

    /**
     * Link Payment Paid
     *
     * Updates the payment status of an invoice to paid.
     *
     * @return \Illuminate\Http\Response
     */
    public function linkPaymentPaid(Request $request)
    {
        $paymentLinkId = $request->input('data.attributes.data.id');
        $paymentMethod = $request->input('data.attributes.data.attributes.payments.0.data.attributes.source.type');

        return $this->paymentService->linkPaymentPaid($paymentLinkId, $paymentMethod);
    }

    /**
     * Source Chargeable
     *
     * Creates a new charge based on the provided source ID and amount.
     *
     * @return \Illuminate\Http\Response
     */
    public function sourceChargeable(Request $request)
    {
        $type = $request->input('data.attributes.data.attributes.type');
        $amount = $request->input('data.attributes.data.attributes.amount');
        $sourceId = $request->input('data.attributes.data.id');

        return $this->paymentService->sourceChargeable($type, $amount, $sourceId);
    }

    /**
     * Get Payment Link
     *
     * Retrieves a payment link based on the provided payment link ID.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws GuzzleException
     */
    public function getPaymentLink(string $id)
    {
        try {
            $paymentLink = $this->paymentService->getPaymentLink($id);
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        return response()->json([
            'data' => $paymentLink,
        ]);
    }

    /**
     * Get Payment Source
     *
     * Retrieves a payment source based on the provided payment source ID.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentSource(string $id)
    {
        try {
            $paymentSource = $this->paymentService->getPaymentSource($id);
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        return response()->json([
            'data' => $paymentSource,
        ]);
    }

    /**
     * Get Payment Intent
     *
     * Retrieves a payment intent based on the provided payment intent ID.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentIntent(string $id)
    {
        try {
            $paymentIntent = $this->paymentService->getPaymentIntent($id);
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        return response()->json([
            'data' => $paymentIntent,
        ]);
    }

    /**
     * Get Payment Methods
     *
     * Retrieves a collection of available payment methods.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws GuzzleException
     */
    public function getPaymentMethods()
    {
        try {
            $paymentMethods = $this->paymentService->getPaymentMethods();
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        return response()->json([
            'data' => $paymentMethods,
        ]);
    }

    /**
     * Get Customer
     *
     * Retrieves customer details for a specific user.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws GuzzleException
     */
    public function getCustomer(Request $request)
    {
        $user = User::find($request->user_id) ?? auth()->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        try {
            $customer = $this->paymentService->getCustomer($user);
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        return response()->json([
            'data' => $customer,
        ]);
    }

    /**
     * Delete Customer
     *
     * Deletes a customer record for a specific user.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws GuzzleException
     */
    public function deleteCustomer(Request $request)
    {
        $user = User::find($request->user_id) ?? auth()->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        try {
            $this->paymentService->deleteCustomer($user);
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        return response()->json([
            'message' => 'Customer deleted',
        ]);
    }

    /**
     * Payment Success Status
     *
     * Displays the payment success page.
     *
     * @return \Illuminate\View\View
     */
    public function paymentSuccessStatus(Request $request)
    {
        $booking = Booking::with('invoice')->findOrFail($request->booking_id);

        return view('payment.success', [
            'booking' => $booking,
            'invoice' => $booking->invoice,
        ]);
    }

    /**
     * Payment Failed Status
     *
     * Displays the payment failed page.
     *
     * @return \Illuminate\View\View
     */
    public function paymentFailedStatus(Request $request)
    {
        $booking = Booking::with('invoice')->findOrFail($request->booking_id);

        return view('payment.failed', [
            'booking' => $booking,
        ]);
    }

    /*---------------------------New Payment Gateway Using Paymongo API------------------------------------*/
    public function cardPayment(CreatePaymentRequest $request)
    {
        $booking = Booking::findOrFail($request->validated('booking_id'));

        try {
            if ($booking->invoice()->exists()) {
                $paymentIntent = $this->bookingPaymentService->getBookingPaymentIntent($booking->invoice->reference_number);
                \Log::info('Card payment already exists', [
                    'booking_id' => $request->validated('booking_id'),
                    'payment_intent' => $paymentIntent,
                ]);
                return response()->json([
                    'message' => 'Payment already exists',
                    'payment_intent' => $paymentIntent,
                    'invoice' => $booking->invoice,
                    'status' => 'error'
                ], 409);
            }

            $payment = $this->bookingPaymentService->createBookingPayment($request->validated());
            if (isset($payment['error'])) {
                $errorDetail = 'Unexpected error occurred while processing the payment.';
                $rawError = $payment['error'];

                if (is_string($rawError)) {
                    if (preg_match('/"detail"\s*:\s*"([^"]*?)(?:\s*\(truncated|\")/', $rawError, $matches)) {
                        $extractedMessage = trim($matches[1]) . ' (truncated...)';
                        if (
                            stripos($extractedMessage, 'declined') !== false || 
                            stripos($extractedMessage, 'reported lost') !== false ||
                            stripos($extractedMessage, 'reported stolen') !== false
                        ) {
                            $errorDetail = 'Your payment was declined. Please try another card or contact your bank.';
                        } else if (stripos($extractedMessage, 'expired') !== false) {
                            $errorDetail = 'Your card has expired. Please use a valid card and try again.';
                        } else if (stripos($extractedMessage, 'format is invalid') !== false) {
                            $errorDetail = 'Invalid card details provided. Please check the card information and try again.';
                        } else if (stripos($extractedMessage, 'CVC number is invalid') !== false) {
                            $errorDetail = 'Invalid CVC number provided. Please check the CVC and try again.';
                        } else if (stripos($extractedMessage, 'fraudulent') !== false) {
                            $errorDetail = 'The payment has been declined. Please contact your bank for more information.';
                        } else if (stripos($extractedMessage, 'insufficient funds') !== false) {
                            $errorDetail = 'Insufficient funds in your account. Please check your balance and try again.';
                        } else if (stripos($extractedMessage, 'cannot be reached') !== false) {
                            $errorDetail = 'The card issuer cannot be reached. Please try again later.';
                        } else if (stripos($extractedMessage, 'blocked') !== false) {
                            $errorDetail = 'The card has been blocked. Please contact your bank for assistance.';
                        } else {
                            $errorDetail = $extractedMessage;
                        }
                    }
                }

                return response()->json([
                    'message' => $payment['error'],
                    'status' => 'error',
                ], $payment['code'] ?? 400);
            }

            $status = $payment['data']['attributes']['status'] ?? 'pending';

            if ($status === 'succeeded') {
                $paymentStatus = 'paid';
            } else {
                $paymentStatus = $status;
            }

            $invoice = $this->bookingCreateService->createBookingInvoice($booking, $payment['data']['id'], $paymentStatus);
            $bookingInvoice = [];
            if ($booking->invoice()->exists()) {
                $bookingInvoice = $this->bookingPaymentService->updateBookingPaymentData($payment['data']['id'], 'card', $paymentStatus);
            }

            $getBookingStatus = $this->bookingPaymentService->getBookingStatus($payment['data']['id']);
            $bookingStatus = null;
            $paymentMethod = null;
            if ($getBookingStatus) {
                $bookingStatus = ucfirst($getBookingStatus['status'] ?? '');
                $paymentMethod = $getBookingStatus['payment_method'] ?? null;
            }
            \Log::info('Card payment successful', [
                'booking_id' => $request->validated('booking_id'),
                'payment' => $payment,
                'invoice' => $invoice,
                'booking_invoice' => $bookingInvoice,
                'booking_status' => $bookingStatus,
                'payment_method' => $paymentMethod,
            ]);
            return response()->json([
                'message' => 'Payment successful',
                'payment' => $payment,
                'invoice' => $invoice,
                'booking_invoice' => $bookingInvoice,
                'status' => 'success',
                'booking_status' => $bookingStatus,
                'payment_method' => $paymentMethod,
            ]);
        } catch (Exception $e) {
            $statusCode = $e->getCode();
            if ($statusCode < 100 || $statusCode > 599) {
                $statusCode = 500;
            }
            \Log::error('Card payment error: ' . $e->getMessage(), [
                'booking_id' => $request->validated('booking_id'),
                'exception' => $e,
            ]);
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
                'status' => 'error',
            ], $statusCode);
        }
    }

    public function ePayment(CreatePaymentSourceRequest $request)
    {
        $booking = Booking::findOrFail($request->validated('booking_id'));

        try {
            if ($booking->invoice()->exists()) {
                $paymentSource = $this->bookingPaymentService->getBookingPaymentSource($booking->invoice->reference_number);

                return response()->json([
                    'message' => 'Payment source already exists',
                    'payment_source' => $paymentSource,
                    'invoice' => $booking->invoice,
                ], 409);
            }

            $paymentSource = $this->bookingPaymentService->checkBookingSource(
                $request->validated('type'),
                $request->validated('amount'),
                $request->validated('booking_id'),
            );

            // Guard clause in case payment source creation fails
            if (!isset($paymentSource['data']['id'])) {
                \Log::error('Invalid payment source structure', $paymentSource);
                return response()->json([
                    'message' => 'Failed to create a valid payment source',
                    'error' => $paymentSource['error'] ?? 'Unexpected response',
                ], $paymentSource['code'] ?? 500);
            }

            // Create invoice using the reference number from the payment source
            $invoice = $this->bookingCreateService->createBookingInvoice($booking, $paymentSource['data']['id']);
        } catch (\Exception $e) {
            \Log::error('ePayment exception: ' . $e->getMessage());
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }

        return response()->json([
            'message' => 'Payment source created',
            'payment_source' => $paymentSource,
            'invoice' => $invoice,
        ]);
    }
    
    public function paymentStatusCheck(Request $request)
    {
        \Log::info('Payment status check request received', ['request' => $request->all()]);
        $eventType = $request->input('data.attributes.type');
        if ($eventType === 'source.chargeable') {
            $type = $request->input('data.attributes.data.attributes.type');
            $amount = $request->input('data.attributes.data.attributes.amount');
            $sourceId = $request->input('data.attributes.data.id');
            $charge = $this->bookingPaymentService->ePaymentChargeable($type, $amount, $sourceId);
            if ($charge['status'] === 'success') {
                \Log::info('Payment charge details for source.chargeable', $charge);
                $this->bookingPaymentService->updateBookingPaymentData($sourceId, $type, 'success');
                return response()->json([
                    'message' => 'Payment source charged successfully',
                    'status' => 'success',
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Payment source charge failed',
                    'status' => 'error',
                ], 400);
            }
        }

        if ($eventType === 'payment.paid') {
            $type = $request->input('data.attributes.data.attributes.source.type');
            $amount = $request->input('data.attributes.data.attributes.amount');
            $sourceId = $request->input('data.attributes.data.attributes.source.id');
            if ($type === 'card') {
                return response()->json([
                    'message' => 'Card payment already handled in-app',
                    'status' => 'ignored',
                ], 200);
            }

            $charge = $this->bookingPaymentService->ePaymentChargeable($type, $amount, $sourceId);
            if ($charge['status'] === 'success') {
                \Log::info('Payment charge details for payment.paid', $charge);
                $this->bookingPaymentService->updateBookingPaymentData($sourceId, null, 'success');
                return response()->json([
                    'message' => 'Payment source charged successfully',
                    'status' => 'success',
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Payment source charge failed',
                    'status' => 'error',
                ], 400);
            }
        }

        if ($eventType === 'payment.failed') {
            $sourceId = $request->input('data.attributes.data.id');
            $this->bookingPaymentService->handlePaymentFailed($sourceId);
            return response()->json([
                'message' => 'Payment failed, source handled',
                'status' => 'error',
            ], 200);
        }

        return response()->json([
            'message' => 'Unhandled event type',
            'status' => 'error',
        ], 200);
    }

    public function ePaymentChargeable(CreatePaymentSourceRequest $request)
    {
        $booking = Booking::findOrFail($request->validated('booking_id'));

        try {
            $bookingId = $booking->id;
            if ($booking->invoice()->exists()) {
                $invoice = $booking->invoice;
            } else {
                $invoice = $this->bookingCreateService->createBookingInvoice($booking, null);
            }

            if ($invoice->reference_number) {
                $paymentSource = $this->bookingPaymentService->getBookingPaymentSource($invoice->reference_number);
            } else {
                //need to create
                // If no reference number, create a new payment source
                $paymentSource = $this->bookingPaymentService->createBookingPaymentSource(
                    $request->validated('type'),
                    $request->validated('amount'),
                    $bookingId,
                );
            }

            $chargeable = $this->bookingPaymentService->ePaymentChargeable(
                $paymentSource['data']['attributes']['type'],
                $paymentSource['data']['attributes']['amount'],
                $paymentSource['data']['id']
            );

            $bookingInvoice = [];
            if ($booking->invoice()->exists()) {
                $bookingInvoice = $this->bookingPaymentService->updateBookingPaymentData($paymentSource['data']['id'], 'gcash');
            }
            return response()->json([
                'message' => 'Payment source charged successfully',
                'chargeable' => $chargeable,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    public function ePaymentPaid(Request $request)
    {
        $paymentSourceId = $request->input('data.attributes.data.id');
        $paymentMethod = $request->input('data.attributes.data.attributes.payments.0.data.attributes.source.type');

        return $this->bookingPaymentService->ePaymentPaid($paymentSourceId, $paymentMethod);
    }

    public function getBookingPaymentSource(Request $request, string $id)
    {
        $type = $request->query('type');
        $bookingStatus = null;
        $paymentMethod = null;
        try {
            $paymentSource = $this->bookingPaymentService->getBookingPaymentSource($id);
            if (!empty($type)) {
                $this->bookingPaymentService->updateBookingPaymentData($id, $type, null);
            }

            $getBookingStatus = $this->bookingPaymentService->getBookingStatus($id);
            if ($getBookingStatus) {
                $bookingStatus = ucfirst($getBookingStatus['status'] ?? '');
                $paymentMethod = $getBookingStatus['payment_method'] ?? null;
            }
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
                'status' => 'error',
            ], $e->getCode());
        }

        \Log::info("response datas:" . json_encode($paymentSource));
        \Log::info("response datas:" . json_encode($bookingStatus));
        \Log::info("response datas:" . json_encode($paymentMethod));
        return response()->json([
            'data' => $paymentSource,
            'status' => 'success',
            'booking_status' => $bookingStatus,
            'payment_method' => $paymentMethod,
        ]);
    }
}
