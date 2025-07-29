<?php

namespace App\Services;

use App\Models\Room;
use App\Models\Addon;
use App\Models\Invoice;
use App\Models\Booking;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Services\NotificationService;
use App\Services\UnavailableDateService;
use App\Events\PaymentFailed;
use App\Events\PaymentSuccessful;

class BookingPaymentProcessService
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

    public function createEPayment(array $data, $bookingId)
    {
        if (empty($data)) {
            return [
                'status' => 'error',
                'message' => 'No payment data provided',
                'code' => 400,
            ];
        }

        $data['description'] = 'Booking Payment for Booking ID: ' . $bookingId;
        $createBookingSource = $this->createBookingSource($data['payment_type'], $data['amount'], $bookingId);
        if ($createBookingSource['status'] === 'error') {
            return [
                'status' => 'error',
                'message' => $createBookingSource['message'],
                'code' => 400,
            ];
        }

        $sourceId = $createBookingSource['data']['id'] ?? null;
        if (!$sourceId) {
            return [
                'status' => 'error',
                'message' => 'Failed to create payment source',
                'code' => 400,
            ];
        }

        $createInvoice = $this->createBookingInvoice(
            Booking::findOrFail($bookingId),
            $sourceId,
            'pending'
        );

        if (!$createInvoice) {
            return [
                'status' => 'error',
                'message' => 'Failed to create booking invoice',
                'code' => 400,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Payment source created successfully',
            'code' => 200,
            'booking_source' => $createBookingSource['data'],
        ];
    }

    public function createBookingSource(string $paymentMethodType, int $amount, string $bookingId)
    {
        $client = new Client();
        try {
            $response = $client->request('POST', "{$this->paymongoUrl}/sources", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'type' => $paymentMethodType,
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

            $statusCode = $response->getStatusCode();
            if (!in_array($statusCode, [200, 201])) {
                return [
                    'status' => 'error',
                    'message' => 'Unexpected status code: ' . $statusCode,
                    'code' => $statusCode,
                ];
            }

            $body = json_decode($response->getBody()->getContents(), true);
            return [
                'status' => 'success',
                'message' => 'Payment source created successfully',
                'code' => $statusCode,
                'data' => $body['data'] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Exception: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    public function createBookingPayment(array $data, $bookingId)
    {
        if (empty($data)) {
            return [
                'status' => 'error',
                'message' => 'No payment data provided',
                'code' => 400,
            ];
        }

        $data['description'] = 'Booking Payment for Booking ID: ' . $bookingId;
        $paymentIntent = $this->createBookingPaymentIntent($data['amount'], $data['description']);
        if ($paymentIntent['status'] === 'error') {
            return [
                'status' => 'error',
                'message' => $paymentIntent['message'],
                'code' => 400,
            ];
        }

        $paymentMethodType = $data['payment_type'];
        if ($paymentMethodType === 'credit/debit_card') {
            $paymentMethodType = 'card';
        } elseif ($paymentMethodType === 'gcash') {
            $paymentMethodType = 'gcash';
        } elseif ($paymentMethodType === 'grab_pay') {
            $paymentMethodType = 'grab_pay';
        } elseif ($paymentMethodType === 'paymaya') {
            $paymentMethodType = 'paymaya';
        }
        
        $paymentMethodDetails = [];
        if ($paymentMethodType === 'card') {
            $paymentMethodDetails = [
                'card_number' => (string) $data['card_number'],
                'exp_month'   => (int) $data['exp_month'],
                'exp_year'    => (int) $data['exp_year'],
                'cvc'         => (string) $data['cvc']
            ];
        }

        $paymentMethod = $this->createBookingPaymentMethod(
            $paymentMethodType,
            $paymentMethodDetails,
            $data['billing_details'],
            $data['billing_address'] ?? []
        );

        if ($paymentMethod['status'] === 'error') {
            return [
                'status' => 'error',
                'message' => $paymentMethod['message'],
                'code' => 400,
            ];
        }

        $attachedPaymentIntent = $this->attachPaymentIntent(
            $paymentIntent['data']['id'],
            $paymentMethod['data']['data']['id']
        );

        if ($attachedPaymentIntent['status'] === 'error') {
            $errorDetail = $this->paymentIntentErrorHandler($attachedPaymentIntent['message'] ?? []);
            return [
                'status' => 'error',
                'message' => $errorDetail,
                'code' => 400,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Payment intent created and attached successfully',
            'code' => 200,
            'data' => $attachedPaymentIntent
        ];
    }

    private function createBookingPaymentIntent(int $amount, string $description)
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
                            'statement_descriptor' => env('APP_NAME', 'Suitescape PH'),
                            'capture_type' => 'automatic',
                        ],
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if (!in_array($statusCode, [200, 201])) {
                return [
                    'status' => 'error',
                    'message' => 'Unexpected status code: ' . $statusCode,
                    'code' => $statusCode,
                ];
            }

            $body = json_decode($response->getBody()->getContents(), true);
            return [
                'status' => 'success',
                'message' => 'Payment intent created successfully',
                'code' => $statusCode,
                'data' => $body['data'] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Exception: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    private function createBookingPaymentMethod(string $paymentMethodType, array $paymentMethodDetails, array $billingDetails, array $billingAddress = [])
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
                    'status' => 'error',
                    'message' => 'Unexpected status code: ' . $statusCode,
                    'code' => $statusCode,
                ];
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['errors'])) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to create payment method: ' . $data['errors'][0]['detail'],
                    'code' => 400,
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Payment method created successfully',
                'code' => $statusCode,
                'data' => $data ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Exception: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    private function attachPaymentIntent(string $paymentIntentId, string $paymentMethodId)
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
                $this->logError('PaymentIntentError', 'error', $errorDetail, $statusCode);
                return [
                    'status' => 'error',
                    'message' => $errorDetail,
                    'code' => 400,
                ];
            }

            if (in_array($statusCode, [200, 201]) && isset($data['data'])) {
                return [
                    'status' => 'success',
                    'message' => 'Payment intent attached successfully',
                    'code' => $statusCode,
                    'data' => $data,
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Unexpected status code: ' . $statusCode,
                    'code' => $statusCode,
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Exception: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    private function paymentIntentErrorHandler(string $message)
    {
        $errorDetail = 'Unexpected error occurred while processing the payment.';
        $errorMessage = $message ?? $errorDetail;
        if (is_string($errorMessage)) {
            if (preg_match('/"detail"\s*:\s*"([^"]*?)(?:\s*\(truncated|\")/', $errorMessage, $matches)) {
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

        return $errorDetail;
    }

    public function convertPaymentMethodValue(string $paymentMethod): string
    {
        switch ($paymentMethod) {
            case 'Gcash':
                return 'gcash';
            case 'GrabPay':
                return 'grab_pay';
            case 'PayMaya':
                return 'paymaya';
            case 'Credit/Debit Card':
                return 'card';
            default:
                return 'unknown';
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

    public function createBookingRecord(string $listingId, array $amount, ?string $message, string $startDate, string $endDate, ?string $couponId)
    {
        $user = auth()->user();

        return $user->bookings()->create([
            'listing_id' => $listingId,
            'coupon_id' => $couponId,
            'amount' => $amount['total'],
            'base_amount' => $amount['base'],
            'message' => $message,
            'date_start' => $startDate,
            'date_end' => $endDate,
        ]);
    }

    public function updateBookingPaymentData(string $paymentLinkId, ?string $paymentMethod = null, ?string $isSuccess = null)
    {
        try {
            $invoice = $this->getInvoiceByReferenceNumber($paymentLinkId);
            $booking = $invoice->booking;
            if (isset($invoice['error'])) {
                return [
                    'status' => 'error',
                    'message' => $invoice['error'],
                    'code' => 404,
                ];
            }

            $bookingStatus = 'upcoming';
            if (Carbon::today()->betweenIncluded($booking->date_start, $booking->date_end)) {
                $bookingStatus = 'ongoing';
            }

            $booking->update(['status' => $bookingStatus]);

            if (!empty($isSuccess) && $isSuccess === 'paid' || $isSuccess === 'success') {
                $paymentStatus = 'paid';
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
                $paymentStatus = 'pending';
                broadcast(new PaymentFailed(
                    $paymentLinkId,
                    'Payment failed'
                ));
            }

            $invoice->update([
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
            ]);

            return [
                'status' => 'success',
                'message' => 'Booking payment data updated successfully',
                'code' => 200,
                'booking_status' => $bookingStatus,
                'payment_status' => $paymentStatus,
            ];
        } catch (\Exception $e) {
            broadcast(new PaymentFailed(
                $paymentLinkId,
                $e->getMessage()
            ));

            return [
                'status' => 'error',
                'message' => 'Failed to update booking payment data: ' . $e->getMessage(),
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
                    'status' => 'error',
                    'message' => $invoice['error'],
                    'code' => 404,
                ];
            }

            if ($invoice->payment_status === 'paid') {
                return [
                    'status' => 'error',
                    'message' => 'Already paid',
                    'code' => 400,
                ];
            }

            $bookingId = $invoice->booking_id;
            $createSourcePayment = $this->createBookingSourcePayment($type, $amount, $sourceId, $bookingId);
            if (isset($createSourcePayment['status']) && $createSourcePayment['status'] === 'success') {
                // $invoice->update([
                //     'payment_status' => 'paid',
                // ]);

                broadcast(new PaymentSuccessful($invoice));

                return [
                    'message' => 'Payment source charged successfully',
                    'invoice' => $invoice,
                    'source_payment' => $createSourcePayment,
                    'status' => 'success',
                ];
            } else {
                return [
                    'message' => $createSourcePayment['message'] ?? 'Failed to create payment source',
                    'status' => 'error',
                    'code' => $createSourcePayment['code'] ?? 500,
                ];
            }
        } catch (\Exception $e) {
            broadcast(new PaymentFailed(
                $sourceId,
                $e->getMessage()
            ));

            return [
                'message' => 'Payment processing failed: ' . $e->getMessage(),
                'status' => 'error',
                'code' => 500,
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
                    'message' => 'Unexpected status code: ' . $statusCode,
                    'code' => $statusCode,
                    'status' => 'error',
                ];
            }
            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['errors'])) {
                return [
                    'message' => 'Failed to create payment: ' . $data['errors'][0]['detail'],
                    'code' => 400,
                    'status' => 'error',
                ];
            }

            $data['status'] = 'success';
            $data['message'] = 'Payment source charged successfully';
            $data['code'] = $statusCode;
            return $data;
        } catch (\Exception $e) {
            return [
                'message' => 'Exception: ' . $e->getMessage(),
                'code' => 500,
                'status' => 'error',
            ];
        }
    }

    public function handleEPaymentFailed(string $sourceId)
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

    public function addBookingRooms($booking, Collection $rooms): void
    {
        foreach ($rooms as $room) {
            $booking->bookingRooms()->create([
                'room_id' => $room->id,
                'name' => $room->name,
                'quantity' => $room->quantity,
                'price' => $room->roomCategory->getCurrentPrice($booking->date_start, $booking->date_end),
            ]);
        }
    }

    public function addBookingAddons($booking, Collection $addons): void
    {
        foreach ($addons as $addon) {
            $booking->bookingAddons()->create([
                'addon_id' => $addon->id,
                'name' => $addon->name,
                'quantity' => $addon->quantity,
                'price' => $addon->price,
            ]);
        }
    }

    public function normalizeRooms(array $roomsData, bool $isEntirePlace): Collection
    {
        $roomIds = array_keys($roomsData);
        $rooms = Room::whereIn('id', $roomIds)->with('roomCategory')->get();
        foreach ($rooms as $room) {
            $roomData = $roomsData[$room->id];

            if (is_array($roomData)) {
                foreach ($roomData as $key => $value) {
                    $room->$key = $value;
                    if ($key === 'name' && empty($value)) {
                        $room->name = $room->roomCategory->name;
                    }
                }
            } else {
                $room->quantity = $roomData;
                if (empty($room->name)) {
                    $room->name = $room->roomCategory->name;
                }
            }
        }

        if (! $isEntirePlace && $rooms->isEmpty()) {
            \Log::error('No rooms found for booking', [
                'rooms' => $roomsData,
            ]);
        }

        return $rooms;
    }

    public function normalizeAddons(array $addonsData): Collection
    {
        $addonIds = array_keys($addonsData);
        $addons = Addon::whereIn('id', $addonIds)->get();
        foreach ($addons as $addon) {
            $addonData = $addonsData[$addon->id];
            if (is_array($addonData)) {
                foreach ($addonData as $key => $value) {
                    $addon->$key = $value;
                }
            } else {
                $addon->quantity = $addonData;
            }
        }

        return $addons;
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
                'status' => 'success',
                'message' => 'Booking status retrieved successfully',
                'payment_method' => $paymentMethod,
                'booking_status' => $status,
                'code' => 200,
            ];
        }
    }
}
