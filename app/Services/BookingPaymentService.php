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
use Illuminate\Support\Facades\Http;

class BookingPaymentService
{
    protected NotificationService $notificationService;

    protected UnavailableDateService $unavailableDateService;

    protected string $paymongoUrl;

    protected string $paymongoSecretKey;

    public function __construct(NotificationService $notificationService, UnavailableDateService $unavailableDateService)
    {
        $this->notificationService = $notificationService;
        $this->unavailableDateService = $unavailableDateService;

        $this->paymongoUrl = config('paymongo.paymongo_api_url');
        $this->paymongoSecretKey = config('paymongo.paymongo_secret_key');
    }

    public function getBookingPaymentIntent(string $paymentIntentId)
    {
        $client = new Client();
        try {
            $response = $client->request('GET', "$this->paymongoUrl/payment_intents/$paymentIntentId", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [
                    'error' => 'Unexpected status code: ' . $response->getStatusCode(),
                    'code' => $response->getStatusCode(),
                ];
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to retrieve payment intent: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    public function createBookingPayment(array $data)
    {
        $paymentIntent = $this->createBookingPaymentIntent(
            $data['amount'],
            $data['description']
        );

        if (!isset($paymentIntent['data']['id'])) {
            return [
                'error' => $paymentIntent['error'] ?? 'Failed to create payment intent',
                'code' => 400,
            ];
        }

        $paymentMethod = $this->createBookingPaymentMethod(
            $data['payment_method_type'],
            $data['payment_method_details'],
            $data['billing_details'],
            $data['billing_address']
        );

        if (!isset($paymentMethod['data']['id'])) {
            return [
                'error' => $paymentMethod['error'] ?? 'Failed to create payment method',
                'code' => 400,
            ];
        }

        $attachedIntent = $this->attachPaymentIntent(
            $paymentIntent['data']['id'],
            $paymentMethod['data']['id']
        );

        if (!isset($attachedIntent['data'])) {
            return [
                'error' => $attachedIntent['error'] ?? 'Failed to attach payment intent',
                'code' => 400,
            ];
        }
        
        return $attachedIntent;
    }

    public function createBookingPaymentIntent(int $amount, string $description)
    {
        $client = new Client();

        try {
            $response = $client->request('POST', "{$this->paymongoUrl}/payment_intents", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'amount' => $amount,
                            'currency' => 'PHP',
                            'payment_method_allowed' => ['card', 'gcash', 'grab_pay', 'paymaya'],
                            'payment_method_options' => [
                                'card' => ['request_three_d_secure' => 'automatic'],
                            ],
                            'description' => $description,
                            'statement_descriptor' => 'Suitescape PH',
                            'capture_type' => 'automatic',
                        ],
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if (!in_array($statusCode, [200, 201])) {
                return [
                    'error' => 'Unexpected status code: ' . $statusCode,
                    'code' => $statusCode,
                ];
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            \Log::error('Failed to create payment intent: ' . $e->getMessage());
            return [
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    public function createBookingPaymentMethod(string $paymentMethodType, array $paymentMethodDetails, array $billingDetails, array $billingAddress = [])
    {
        $client = new Client();

        try {
            $response = $client->request('POST', "{$this->paymongoUrl}/payment_methods", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'type' => $paymentMethodType,
                            'details' => $paymentMethodDetails,
                            'billing' => array_merge($billingDetails, [
                                'address' => $billingAddress,
                            ]),
                        ],
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if (!in_array($statusCode, [200, 201])) {
                return [
                    'error' => 'Unexpected status code: ' . $statusCode,
                    'code' => $statusCode,
                ];
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['errors'])) {
                \Log::error('Failed to create payment method: ' . $data['errors'][0]['detail']);
                return [
                    'error' => 'Failed to create payment method: ' . $data['errors'][0]['detail'],
                    'code' => 400,
                ];
            }

            return $data;

        } catch (\Exception $e) {
            return [
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    public function createBookingInvoice(Booking $booking, string $referenceNumber, string $paymentStatus = 'pending')
    {
        return $booking->invoice()->create([
            'user_id' => $booking->user_id,
            'coupon_id' => $booking->coupon_id,
            'coupon_discount_amount' => $booking->coupon->discount_amount ?? 0,
            'reference_number' => $referenceNumber,
            'payment_status' => $paymentStatus,
        ]);
    }

    public function attachPaymentIntent(string $paymentIntentId, string $paymentMethodId)
    {
        $client = new Client();

        try {
            $response = $client->request('POST', "{$this->paymongoUrl}/payment_intents/{$paymentIntentId}/attach", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'payment_method' => $paymentMethodId,
                        ],
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['errors']) && is_array($data['errors'])) {
                $errorDetail = $data['errors'][0]['detail'] ?? 'Unknown error occurred.';
                $errorDetail = str_replace('purchase', 'booking', $errorDetail);

                return [
                    'error' => $errorDetail,
                    'code' => 400,
                ];
            }

            if (in_array($statusCode, [200, 201]) && isset($data['data'])) {
                return $data;
            }

            return [
                'error' => 'Unexpected response structure.',
                'code' => $statusCode,
            ];

        } catch (\Exception $e) {
            return [
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    public function updateBookingPaymentData(string $paymentLinkId, ?string $paymentMethod = null, ?string $isSuccess = null)
    {
        try {
            $invoice = $this->getInvoiceByReferenceNumber($paymentLinkId);
            $booking = $invoice->booking;
            if (isset($invoice['error'])) {
                return response($invoice['error'], 200);
            }

            if (Carbon::today()->betweenIncluded($booking->date_start, $booking->date_end)) {
                $booking->update(['status' => 'ongoing']);
            } else {
                $booking->update(['status' => 'upcoming']);
            }

            if (!empty($paymentMethod)) {
                $invoice->update([
                    'payment_method' => $paymentMethod,
                    'payment_status' => $isSuccess,
                ]);
            } else {
                if (!empty($isSuccess) && $isSuccess === 'paid' || $isSuccess === 'success') {
                    $invoice->update([
                        'payment_status' => 'paid',
                    ]);
                } else {
                    $invoice->update([
                        'payment_status' => 'pending',
                    ]);
                }
            }

            if (!empty($isSuccess) && $isSuccess === 'paid' || $isSuccess === 'success') {
                $this->updateAdditionalPayments($invoice);
                if ($booking->listing->is_entire_place) {
                    $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'listing', $booking->listing->id, $booking->date_start, $booking->date_end);
                } else {
                    foreach ($booking->rooms as $room) {
                        $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'room', $room->id, $booking->date_start, $booking->date_end);
                    }
                }

                $this->notificationService->createNotification([
                    'user_id' => $booking->user_id,
                    'title' => 'Booking Successfully Paid!',
                    'message' => "Your booking for \"{$booking->listing->name}\" has been paid successfully.",
                    'type' => 'booking',
                    'action_id' => $booking->id,
                ]);

                broadcast(new PaymentSuccessful($invoice));
            } else {
                $invoice->update([
                    'payment_status' => 'pending',
                ]);

                broadcast(new PaymentFailed(
                    $paymentLinkId,
                    'Payment failed'
                ));
            }

            return response()->noContent();
        } catch (\Exception $e) {
            broadcast(new PaymentFailed(
                $paymentLinkId,
                $e->getMessage()
            ));

            return response('Payment processing failed', 200);
        } 
    }

    public function getBookingPaymentSource(string $sourceId)
    {
        $client = new Client();
        try {
            $response = $client->request('GET', "{$this->paymongoUrl}/sources/{$sourceId}", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [
                    'error' => 'Unexpected status code: ' . $response->getStatusCode(),
                    'code' => $response->getStatusCode(),
                ];
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to retrieve payment source: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    public function checkBookingSource(string $type, float $amount, string $bookingId)
    {
        $source = $this->createBookingSource($type, $amount, $bookingId);

        if (!isset($source['data']['id'])) {
            return [
                'error' => $source['error'] ?? 'Failed to create payment source',
                'code' => $source['code'] ?? 400,
            ];
        }

        return $source;
    }

    private function createBookingSource(string $type, float $amount, string $bookingId)
    {
        \Log::info('Creating booking source with type: ' . $type . ', amount: ' . $amount . ', bookingId: ' . $bookingId);

        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->request('POST', "{$this->paymongoUrl}/sources", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'type' => $type,
                            'amount' => $amount,
                            'currency' => 'PHP',
                            'redirect' => [
                                'success' => route('payment.success', ['booking_id' => $bookingId]),
                                'failed' => route('payment.failed', ['booking_id' => $bookingId]),
                            ],
                        ],
                    ],
                ],
            ]);

            $body = $response->getBody()->getContents(); // only call once
            \Log::info('Create Booking Source Response: ', [
                'status_code' => $response->getStatusCode(),
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            if (!in_array($statusCode, [200, 201])) {
                return [
                    'error' => 'Unexpected status code: ' . $statusCode,
                    'code' => $statusCode,
                ];
            }

            return json_decode($body, true);
        } catch (\Exception $e) {
            \Log::error('Failed to create booking source: ' . $e->getMessage());
            return [
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    public function ePaymentChargeable(string $type, int $amount, string $sourceId)
    {
        try {
            $invoice = $this->getInvoiceByReferenceNumber($sourceId);
            if (isset($invoice['error'])) {
                return [
                    'error' => $invoice['error'],
                    'status' => 'error',
                ];
            }

            if ($invoice->payment_status === 'paid') {
                return [
                    'message' => 'Already paid',
                    'status' => 'error',
                ];
            }

            $bookingId = $invoice->booking_id;
            $createSourcePayment = $this->createBookingSourcePayment($type, $amount, $sourceId, $bookingId);
            if (isset($createSourcePayment['status'])) {
                $invoice->update([
                    'payment_status' => 'paid',
                ]);

                broadcast(new PaymentSuccessful($invoice));

                return [
                    'message' => 'Payment successful',
                    'invoice' => $invoice,
                    'source_payment' => $createSourcePayment,
                    'status' => 'success',
                ];
            } else {
                return [
                    'error' => $createSourcePayment['error'] ?? 'Failed to create payment source',
                    'status' => 'error',
                ];
            }
        } catch (\Exception $e) {
            broadcast(new PaymentFailed(
                $sourceId,
                $e->getMessage()
            ));

            return [
                'error' => 'Payment processing failed: ' . $e->getMessage(),
                'status' => 'error',
            ];
        }
    }

    private function createBookingSourcePayment(string $type, int $amount, string $sourceId, string $bookingId)
    {
        $client = new Client();
        try {
            $response = $client->request('POST', "{$this->paymongoUrl}/payments", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'amount' => $amount,
                            'currency' => 'PHP',
                            'description' => "Payment for $bookingId",
                            'source' => [
                                'id' => $sourceId,
                                'type' => 'source',
                            ],
                        ],
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if (!in_array($statusCode, [200, 201])) {
                return [
                    'error' => 'Unexpected status code: ' . $statusCode,
                    'code' => $statusCode,
                    'status' => 'error',
                ];
            }
            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['errors'])) {
                return [
                    'error' => 'Failed to create payment: ' . $data['errors'][0]['detail'],
                    'code' => 400,
                    'status' => 'error',
                ];
            }

            $data['status'] = 'success';
            return $data;
        } catch (\Exception $e) {
            return [
                'error' => 'Exception: ' . $e->getMessage(),
                'code' => 500,
                'status' => 'error',
            ];
        }
    }

    public function handlePaymentFailed(string $sourceId)
    {
        try {
            $invoice = $this->getInvoiceByReferenceNumber($sourceId);
            if (isset($invoice['error'])) {
                return response($invoice['error'], 200);
            }

            $invoice->update([
                'payment_status' => 'failed',
            ]);

            broadcast(new PaymentFailed(
                $sourceId,
                'Payment failed'
            ));

            return response()->noContent();
        } catch (\Exception $e) {
            return response('Payment processing failed', 200);
        }
    }

    public function createBookingPaymentSource(string $type, float $amount, string $bookingId)
    {
        $source = $this->createBookingSource($type, $amount, $bookingId);

        if (!isset($source['data']['id'])) {
            return [
                'error' => $source['error'] ?? 'Failed to create payment source',
                'code' => 400,
            ];
        }

        return $source;
    }

    public function getBookingStatus(string $paymentLinkId)
    {
        $invoice = $this->getInvoiceByReferenceNumber($paymentLinkId);
        $booking = $invoice->booking;
        $status = $booking->status;
        $invoicePaymentMethod = $invoice->payment_method;
        $paymentMethod = null;
        if (!empty($invoicePaymentMethod)) {
            if ($invoicePaymentMethod === 'gcash') {
                $paymentMethod = 'GCash';
            } elseif ($invoicePaymentMethod === 'grab_pay') {
                $paymentMethod = 'GrabPay';
            } elseif ($invoicePaymentMethod === 'paymaya') {
                $paymentMethod = 'PayMaya';
            } elseif ($invoicePaymentMethod === 'card') {
                $paymentMethod = 'Card';
            }
        }

        if (isset($status)) {
            return [
                'status' => $status,
                'message' => 'Booking status retrieved successfully',
                'payment_method' => $paymentMethod,
            ];
        }
    }

    private function getInvoiceByReferenceNumber(string $referenceNumber)
    {
        $invoice = Invoice::where('reference_number', $referenceNumber)->first();
        if (! $invoice) {
            return [
                'error' => 'Invoice not found',
            ];
        }

        return $invoice;
    }

    /*-----------------------------------------------------------------------*/
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

            $this->notificationService->createNotification([
                'user_id' => $booking->user_id,
                'title' => 'Booking Successfully Paid!',
                'message' => "Your booking for \"{$booking->listing->name}\" has been paid successfully.",
                'type' => 'booking',
                'action_id' => $booking->id,
            ]);

            broadcast(new PaymentSuccessful($invoice));

            return response()->noContent();
        } catch (\Exception $e) {
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
            $createSourcePayment = $this->createSourcePayment($type, $amount, $sourceId);

            // Update the invoice payment status
            $invoice->update([
                'payment_status' => 'paid',
            ]);

            broadcast(new PaymentSuccessful($invoice));

            return response()->noContent();
        } catch (\Exception $e) {
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
        // $payload = [
        //     'data' => [
        //         'attributes' => [
        //             'amount' => $amount,
        //             'currency' => 'PHP',
        //             'description' => "Payment for $type",
        //             'source' => [
        //                 'id' => $sourceId,
        //                 'type' => 'source',
        //             ],
        //         ]
        //     ]
        // ];

        // \Log::info('Sending to PayMongo payment()', $payload);

        // return Paymongo::payment()->create($payload);
        // $payment = Paymongo::payment()
        //     ->create([
        //         'amount' => 747078,
        //         'currency' => 'PHP',
        //         'description' => 'Testing payment',
        //         'statement_descriptor' => 'Test Paymongo api',
        //         'source' => [
        //             'id' => $sourceId,
        //             'type' => 'source'
        //         ]
        //     ]);
        // $payment = Paymongo::payment()
        //     ->create([
        //         'amount' => 100.00,
        //         'currency' => 'PHP',
        //         'description' => 'Testing payment',
        //         'statement_descriptor' => 'Test Paymongo',
        //         'source' => [
        //             'id' => $sourceId,
        //             'type' => 'source'
        //         ]
        //     ]);
        
        // \Log::info('Payment created', [
        //     'amount' => $amount,
        //     'source_id' => $sourceId,
        //     'payment' => $payment->getData(),
        // ]);
        // return $payment;
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


    //for testing
    public function createGcashSource(array $data)
    {
        return Paymongo::source()->create([
            'type' => 'gcash',
            'amount' => $data['amount'],           // amount in centavos
            'currency' => 'PHP',
            'redirect' => [
                'success' => $data['redirect_success'],
                'failed' => $data['redirect_failed'],
            ],
            'billing' => [
                'name' => $data['billing']['name'],
                'email' => $data['billing']['email'],
                'phone' => $data['billing']['phone'],
                'address' => [
                    'line1' => $data['billing_address']['line1'],
                    'line2' => $data['billing_address']['line2'] ?? '',
                    'city' => $data['billing_address']['city'],
                    'state' => $data['billing_address']['state'],
                    'postal_code' => $data['billing_address']['postal_code'],
                    'country' => $data['billing_address']['country'],
                ],
            ],
        ]);
    }
}
