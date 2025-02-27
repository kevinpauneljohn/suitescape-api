<?php

namespace App\Services;

use App\Events\PaymentFailed;
use App\Events\PaymentSuccessful;
use App\Models\Invoice;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Carbon;
use Luigel\Paymongo\Facades\Paymongo;

class PaymentService
{
    protected UnavailableDateService $unavailableDateService;

    public function __construct(UnavailableDateService $unavailableDateService)
    {
        $this->unavailableDateService = $unavailableDateService;
    }

    public function createPaymentLink(float $amount, string $description)
    {
        return $this->createLink($amount, $description)->getData();
    }

    public function createPaymentSource(string $type, float $amount, string $bookingId)
    {
       return $this->createSource($type, $amount, $bookingId)->getData();
    }

    /**
     * @throws GuzzleException
     */
    public function createPayment(array $data)
    {
        $paymentIntent = $this->createPaymentIntent(
            $data['amount'],
            $data['description']
        );

        $paymentMethod = $this->createPaymentMethod(
            $data['payment_method_type'],
            $data['payment_method_details'],
            $data['billing_details'],
            $data['billing_address']
        );

        $paymentIntent = $paymentIntent->attach($paymentMethod->getData()['id']);

        return $paymentIntent->getData();
    }

    public function archivePaymentLink(string $paymentLinkId)
    {
        $paymentLink = Paymongo::link()->find($paymentLinkId);

        $paymentLink->archive();
    }

    public function linkPaymentPaid(string $paymentLinkId, string $paymentMethod)
    {
        try {
            // Get the invoice
            $invoice = $this->getInvoiceByReferenceNumber($paymentLinkId);

            // Get the booking
            $booking = $invoice->booking;

            // Return any error messages
            if (isset($invoice['error'])) {
                return response($invoice['error'], 200);
            }

            // Update the invoice payment status
            $invoice->update([
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
            ]);

            // Update additional payments
            $this->updateAdditionalPayments($invoice);

            // Add unavailable dates for the booking
            if ($booking->listing->is_entire_place) {
                $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'listing', $booking->listing->id, $booking->date_start, $booking->date_end);
            } else {
                foreach ($booking->rooms as $room) {
                    $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'room', $room->id, $booking->date_start, $booking->date_end);
                }
            }

            // Update the booking status
            if (Carbon::today()->betweenIncluded($booking->date_start, $booking->date_end)) {
                $booking->update(['status' => 'ongoing']);
            } else {
                $booking->update(['status' => 'upcoming']);
            }

            \Log::info('Invoice marked as paid', [
                'invoice_id' => $invoice->id,
                'reference_number' => $paymentLinkId,
            ]);

            broadcast(new PaymentSuccessful($invoice));

            return response()->noContent();
        } catch (\Exception $e) {
            \Log::error('Payment processing failed', [
                'reference_number' => $paymentLinkId,
                'message' => $e->getMessage(),
            ]);

            broadcast(new PaymentFailed(
                $paymentLinkId,
                $e->getMessage()
            ));

            return response('Payment processing failed', 200);
        }
    }

    public function sourceChargeable(string $type, int $amount, string $sourceId)
    {
        try {
            // Get the invoice
            $invoice = $this->getInvoiceByReferenceNumber($sourceId);

            // Return any error messages
            if (isset($invoice['error'])) {
                return response($invoice['error'], 200);
            }

            // Create payment using the source
            $this->createSourcePayment($type, $amount, $sourceId);

            // Update the invoice payment status
            $invoice->update([
                'payment_status' => 'paid',
            ]);

            \Log::info('Invoice marked as paid', [
                'invoice_id' => $invoice->id,
                'reference_number' => $sourceId,
            ]);

            broadcast(new PaymentSuccessful($invoice));

            return response()->noContent();
        } catch (\Exception $e) {
            \Log::error('Payment processing failed', [
                'reference_number' => $sourceId,
                'message' => $e->getMessage(),
            ]);

            broadcast(new PaymentFailed(
                $sourceId,
                $e->getMessage()
            ));

            return response('Payment processing failed', 200);
        }
    }

    public function getPaymentLink(string $paymentLinkId)
    {
        return Paymongo::link()->find($paymentLinkId)->getData();
    }

    public function getPaymentSource(string $paymentSourceId)
    {
        return Paymongo::source()->find($paymentSourceId)->getData();
    }

    public function getPaymentIntent(string $paymentIntentId)
    {
        return Paymongo::paymentIntent()->find($paymentIntentId)->getData();
    }

    /**
     * @throws GuzzleException
     */
    public function getPaymentMethods()
    {
        $user = auth()->user();

        return $this->getCustomer($user)->paymentMethods();
    }

    /**
     * @throws GuzzleException
     */
    public function getCustomer(User $user)
    {
        if ($user->paymongo_customer_id) {
            return Paymongo::customer()->find($user->paymongo_customer_id);
        }

        // Try to search for the customer first, if not found, create a new one
        $customer = $this->searchCustomer($user->email);

        if ($customer['data']) {
            // Get the first customer found
            $customerId = $customer['data'][0]['id'];

            $user->update([
                'paymongo_customer_id' => $customerId,
            ]);

            return Paymongo::customer()->find($customerId);
        }

        return $this->createCustomer($user);
    }

    /**
     * @throws GuzzleException
     */
    public function searchCustomer(string $email)
    {
        $client = new Client;

        $response = $client->request('GET', "https://api.paymongo.com/v1/customers?email=$email", [
            'headers' => [
                'accept' => 'application/json',
                'authorization' => 'Basic '.base64_encode(config('paymongo.secret_key')),
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    //    public function updateCustomer(User $user)
    //    {
    //        $customer = $this->getCustomer($user);
    //
    //        $customer->update([
    //            'first_name' => $user->firstname,
    //            'last_name' => $user->lastname,
    //            'phone' => $user->mobile_number,
    //            'email' => $user->email,
    //        ]);
    //
    //        return $customer;
    //    }

    /**
     * @throws GuzzleException
     */
    public function deleteCustomer(User $user): void
    {
        $customer = $this->getCustomer($user);

        $customer->delete();

        $user->update([
            'paymongo_customer_id' => null,
        ]);
    }

    private function createCustomer(User $user)
    {
        $customer = Paymongo::customer()->create([
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'phone' => $user->mobile_number,
            'email' => $user->email,
            'default_device' => 'email',
        ]);

        $user->update([
            'paymongo_customer_id' => $customer->getData()['id'],
        ]);

        return $customer;
    }

    private function createLink(float $amount, string $description)
    {
        return Paymongo::link()->create([
            'amount' => $amount,
            'description' => $description,
            'remarks' => 'Suitescape PH',
        ]);
    }

    private function createSource(string $type, float $amount, string $bookingId)
    {
        return Paymongo::source()->create([
            'type' => $type,
            'amount' => $amount,
            'currency' => 'PHP',
            'redirect' => [
                'success' => route('payment.success-status', ['booking_id' => $bookingId]),
                'failed' => route('payment.failed-status', ['booking_id' => $bookingId]),
            ],
        ]);
    }

    private function createSourcePayment(string $type, int $amount, string $sourceId)
    {
        return Paymongo::payment()->create([
            'amount' => $amount,
            'source' => [
                'id' => $sourceId,
                'type' => 'source',
            ],
            'currency' => 'PHP',
            'description' => "Payment for $type",
        ]);
    }

    private function createPaymentIntent(float $amount, string $description)
    {
        //        $user = auth()->user();
        //        $customer = $this->getCustomer($user);

        return Paymongo::paymentIntent()->create([
            'amount' => $amount,
            'payment_method_allowed' => ['card', 'gcash', 'paymaya'],
            'payment_method_options' => [
                'card' => [
                    'request_three_d_secure' => 'automatic',
                ],
            ],
            'description' => $description,
            'statement_descriptor' => 'Suitescape PH',
            'currency' => 'PHP',
            //            'capture_type' => 'manual',
            //            'setup_future_usage' => [
            //                'session_type' => 'on_session',
            //                'customer_id' => $customer->getData()['id'],
            //            ],
        ]);
    }

    private function createPaymentMethod(
        string $paymentMethodType,
        array $paymentMethodDetails,
        array $billingDetails,
        array $billingAddress
    ) {
        return Paymongo::paymentMethod()->create([
            'type' => $paymentMethodType,
            'details' => $paymentMethodDetails,
            'billing' => array_merge($billingDetails, [
                'address' => $billingAddress,
            ]),
        ]);
    }

    private function getInvoiceByReferenceNumber(string $referenceNumber)
    {
        $invoice = Invoice::where('reference_number', $referenceNumber)->first();

        // Check if the invoice exists
        if (! $invoice) {
            \Log::warning('Payment received for non-existent invoice', [
                'reference_number' => $referenceNumber,
            ]);

            return [
                'error' => 'Invoice not found',
            ];
        }

        // Check if the invoice is already paid
        //        if ($invoice->payment_status === 'paid') {
        //            \Log::info('Duplicate payment notification received', [
        //                'invoice_id' => $invoice->id,
        //                'reference_number' => $referenceNumber,
        //            ]);
        //
        //            return [
        //                'error' => 'Invoice already paid',
        //            ];
        //        }

        return $invoice;
    }

    private function updateAdditionalPayments($invoice)
    {
        $pendingAdditionalPayments = collect($invoice->pending_additional_payments);
        $paidAdditionalPayments = collect($invoice->paid_additional_payments);

        // Update the additional payment status
        if ($pendingAdditionalPayments->isNotEmpty()) {
            $currentPendingPayment = $pendingAdditionalPayments->last();
            $pendingPaymentIndex = $pendingAdditionalPayments->keys()->last();

            $invoice->update([
                // Remove the last pending payment
                'pending_additional_payments' => $pendingAdditionalPayments->forget($pendingPaymentIndex)->toArray(),

                // Add the last pending payment to the paid additional payments
                'paid_additional_payments' => $paidAdditionalPayments->push($currentPendingPayment)->toArray(),
            ]);
        }
    }
}
