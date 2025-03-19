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
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private BookingCreateService $bookingCreateService;

    private BookingUpdateService $bookingUpdateService;

    private PaymentService $paymentService;

    public function __construct(BookingCreateService $bookingCreateService, BookingUpdateService $bookingUpdateService, PaymentService $paymentService)
    {
        $this->middleware('auth:sanctum')->except(['linkPaymentPaid', 'sourceChargeable', 'paymentSuccessStatus', 'paymentFailedStatus']);

        $this->bookingCreateService = $bookingCreateService;
        $this->bookingUpdateService = $bookingUpdateService;
        $this->paymentService = $paymentService;
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

                \Log::info('Payment link already exists', $paymentLink);

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
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        // Create invoice
        $invoice = $this->bookingCreateService->createBookingInvoice($request->validated('booking_id'), $payment['id']);

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
}
