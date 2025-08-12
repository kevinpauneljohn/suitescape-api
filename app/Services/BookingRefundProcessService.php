<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Models\BookingCancellation;

class BookingRefundProcessService
{
    protected string $paymongoUrl;
    protected string $paymongoSecretKey;

    public function __construct()
    {
        $this->paymongoUrl = config('paymongo.paymongo_api_url');
        $this->paymongoSecretKey = config('paymongo.paymongo_secret_key');
    }

    /**
     * Refund a payment via PayMongo.
     *
     * @param string $paymentId
     * @param int|null $amount Amount to refund in centavos (e.g., 1000 = ₱10.00). Null means full refund.
     * @return array
     */
    public function refundPayment(string $paymentId, int $amount = null): array
    {
        $client = new Client();
        $attributes = ['payment_id' => $paymentId];
        if (!is_null($amount)) {
            $attributes['amount'] = $amount;
        }

        try {
            $payload = [
                'data' => [
                    'attributes' => [
                        'payment_id' => $paymentId,
                        'reason' => 'requested_by_customer',
                    ],
                ],
            ];

            if (!is_null($amount)) {
                $payload['data']['attributes']['amount'] = $amount;
            }

            $response = $client->post("{$this->paymongoUrl}/refunds", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            if (!in_array($statusCode, [200, 201])) {
                return [
                    'status'  => 'error',
                    'message' => 'Unexpected status code: ' . $statusCode,
                    'code'    => $statusCode,
                ];
            }

            $body = json_decode($response->getBody()->getContents(), true);
            return [
                'status'  => 'success',
                'message' => 'Booking has been successfully refunded.',
                'code'    => $statusCode,
                'data'    => $body['data'] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'error',
                'message' => 'Exception: ' . $e->getMessage(),
                'code'    => 500,
            ];
        }
    }

    public function createBookingCancellation($bookingId, $userId, array $bookingCancellationData)
    {
        $attributes = $bookingCancellationData['attributes'] ?? [];
        $amount = isset($attributes['amount']) 
            ? $attributes['amount'] / 100 
            : 0;

        $bookingCancellation = BookingCancellation::create([
            'booking_id'  => $bookingId,
            'user_id'     => $userId,
            'payment_id'  => $attributes['payment_id'] ?? null,
            'amount'      => $amount,
            'refund_id'   => $bookingCancellationData['id'] ?? null,
            'status'      => $attributes['status'] ?? 'pending',
            'currency'    => $attributes['currency'] ?? 'PHP',
            'refunded_at' => $attributes['refunded_at'] ?? null,
        ]);

        return $bookingCancellation;
    }
}
