<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\CreatePayoutMethodRequest;
use App\Http\Resources\PayoutMethodResource;
use App\Models\User;
use App\Services\BookingCreateService;
use App\Services\PaymentService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private BookingCreateService $bookingCreateService;

    private PaymentService $paymentService;

    public function __construct(BookingCreateService $bookingCreateService, PaymentService $paymentService)
    {
        $this->bookingCreateService = $bookingCreateService;
        $this->paymentService = $paymentService;
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
        try {
            $payment = $this->paymentService->createPayment($request->validated());
        } catch (Exception $e) {
            return response()->json([
                'is_paymongo_error' => true,
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        $invoice = $this->bookingCreateService->createBookingInvoice($request->validated()['booking_id']);

        return response()->json([
            'message' => 'Payment successful',
            'payment' => $payment,
            'invoice' => $invoice,
        ]);
    }

    public function addPayoutMethod(CreatePayoutMethodRequest $request)
    {
        $payoutMethod = $this->paymentService->addPayoutMethod($request->validated());

        return response()->json([
            'message' => 'Payout method added',
            'payout_method' => $payoutMethod,
        ]);
    }

    /**
     * Get Payout Methods
     *
     * Retrieves a collection of available payout methods.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPayoutMethods()
    {
        $payoutMethods = $this->paymentService->getPayoutMethods();

        return response()->json([
            'payout_methods' => PayoutMethodResource::collection($payoutMethods),
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
            'payment_methods' => $paymentMethods,
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
        $user = User::find($request->user_id) ?? auth('sanctum')->user();

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
            'customer' => $customer,
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
        $user = User::find($request->user_id) ?? auth('sanctum')->user();

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
}
