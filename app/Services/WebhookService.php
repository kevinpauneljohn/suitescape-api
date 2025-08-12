<?php

namespace App\Services;

use App\Models\Webhook;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class WebhookService
{
    protected string $paymongoUrl;
    protected string $paymongoSecretKey;
    public function __construct()
    {
        $this->paymongoUrl = config('paymongo.paymongo_api_url');
        $this->paymongoSecretKey = config('paymongo.paymongo_secret_key');
    }

    public function createWebhook(array $attributes)
    {
        return DB::transaction(function () use ($attributes) {
            try {
                $client = new Client();
                $payload = [
                    'data' => [
                        'attributes' => $attributes,
                    ],
                ];

                $response = $client->post("{$this->paymongoUrl}/webhooks", [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $payload,
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                if (isset($data['errors'])) {
                    return $this->returnResponse('error', 'Failed to create webhook: ' . $data['errors'][0]['detail'], 422);    
                }

                $createWebhook = $this->storeAction($data);
                if ($createWebhook['status'] === 'error') {
                    return $this->returnResponse('error', $createWebhook['message'], $createWebhook['code']);
                }
                return $createWebhook;
            } catch (Exception $e) {
                return $this->returnResponse('error', 'Failed to create webhook: ' . $e->getMessage(), 500);
            }
        });
    }

    public function storeAction(array $data)
    {
        $createWebhook = Webhook::create([
            'hook_id'              => $data['data']['id'],
            'disabled_reason' => null,
            'events'          => json_encode($data['data']['attributes']['events'] ?? []),
            'livemode'        => $data['data']['attributes']['livemode'] ?? false,
            'secret_key'      => $data['data']['attributes']['secret_key'] ?? null,
            'status'          => $data['data']['attributes']['status'] ?? null,
            'url'             => $data['data']['attributes']['url'] ?? null,
            'paymongo_created_at'   => $data['data']['attributes']['created_at'] ?? null,
            'paymongo_updated_at'   => $data['data']['attributes']['updated_at'] ?? null,
        ]);

        if (!$createWebhook) {
            return $this->returnResponse('error', 'Failed to create webhook in local database', 500);
        }

        return $this->returnResponse('success', 'Webhook stored successfully', 201, $createWebhook->toArray());
    }

    public function updateWebhook(array $attributes, string $id)
    {
        return DB::transaction(function () use ($attributes, $id) {
            try {
                $client = new Client();
                $payload = [
                    'data' => [
                        'attributes' => $attributes,
                    ],
                ];

                $response = $client->put("{$this->paymongoUrl}/webhooks/{$id}", [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $payload,
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                if (isset($data['errors'])) {
                    return $this->returnResponse(
                        'error',
                        'Failed to update webhook: ' . $data['errors'][0]['detail'],
                        422
                    );    
                }

                $updateWebhook = $this->updateAction($data);
                if ($updateWebhook) {
                    if ($updateWebhook['status'] === 'error') {
                        return $this->returnResponse('error', $updateWebhook['message'], $updateWebhook['code']);
                    }

                    return $this->returnResponse('success', 'Webhook updated successfully', 200, $updateWebhook);
                }
            } catch (Exception $e) {
                return $this->returnResponse('error', 'Failed to update webhook: ' . $e->getMessage(), 500);
            }
        });
    }

    public function updateAction(array $data)
    {
        $webhook = Webhook::where('hook_id', $data['data']['id'])->first();
        if (!$webhook) {
            return $this->returnResponse('error', 'Webhook not found in local database', 404);
        }

        $webhook->update([
            'events'          => json_encode($data['data']['attributes']['events'] ?? []),
            'url'             => $data['data']['attributes']['url'] ?? null,
            'paymongo_created_at'   => $data['data']['attributes']['created_at'] ?? null,
            'paymongo_updated_at'   => $data['data']['attributes']['updated_at'] ?? null,
        ]);

        return $this->returnResponse('success', 'Webhook updated successfully', 200, $webhook->toArray());
    }

    public function getAllWebhooksFromApi(): array
    {
        try {
            $client = new Client();
            $response = $client->get("{$this->paymongoUrl}/webhooks", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data'] ?? [];
        } catch (Exception $e) {
            return $this->returnResponse('error', 'Failed to retrieve webhooks: ' . $e->getMessage(), 500);
        }
    }

    public function getWebhookApiById(string $id)
    {
        try {
            $client = new Client();
            $response = $client->get("{$this->paymongoUrl}/webhooks/{$id}", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['errors'])) {
                return $this->returnResponse('error', 'Failed to retrieve webhook: ' . $data['errors'][0]['detail'], 404);
            }
            return $data['data'] ?? null;
        } catch (Exception $e) {
            return $this->returnResponse('error', 'Failed to retrieve webhook: ' . $e->getMessage(), 500);
        }
    }

    public function deleteWebhookApiById(string $id)
    {
        try {
            $client = new Client();
            $response = $client->delete("{$this->paymongoUrl}/webhooks/{$id}", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 204) {
                $webhook = $this->deleteWebhookById($id);
                if ($webhook) {
                    return $this->returnResponse('success', 'Webhook deleted successfully', 204);
                }
            }

            return $this->returnResponse('error', 'Failed to delete webhook', 500);
        } catch (Exception $e) {
            return $this->returnResponse('error', 'Failed to delete webhook: ' . $e->getMessage(), 500);
        }
    }

    public function deleteWebhookById(string $id)
    {
        $webhook = Webhook::where('hook_id', $id)->first();
        if (!$webhook) {
            return $this->returnResponse('error', 'Webhook not found', 404);
        }

        if ($webhook->delete()) {
            return $this->returnResponse('success', 'Webhook deleted successfully', 204);
        }

        return $this->returnResponse('error', 'Failed to delete webhook', 500);
    }

    public function changeWebhookStatus(string $id, string $status)
    {
        try {
            $client = new Client();


            $response = $client->post("{$this->paymongoUrl}/webhooks/{$id}/{$status}", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->paymongoSecretKey),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                return $this->returnResponse('error', 'Failed to change webhook status', $response->getStatusCode());
            }

            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['errors'])) {
                return $this->returnResponse('error', 'Failed to change webhook status: ' . $data['errors'][0]['detail'], 422);
            }  

            $webhook = $this->changeWebhookStatusById($id, $data['data']['attributes']['status']);
            if (!$webhook) {
                return $this->returnResponse('error', 'Failed to change webhook status in local database', 500);
            }

            return $webhook;
        } catch (Exception $e) {
            return $this->returnResponse('error', 'Failed to change webhook status: ' . $e->getMessage(), 500);
        }
    }

    public function changeWebhookStatusById(string $id, string $status)
    {
        try {
            $webhook = Webhook::where('hook_id', $id)->first();
            if (!$webhook) {
                return $this->returnResponse('error', 'Webhook not found', 404);
            }

            $webhook->status = $status;
            $webhook->save();

            return $webhook;
        } catch (Exception $e) {
            return $this->returnResponse('error', 'Failed to change webhook status: ' . $e->getMessage(), 500);
        }
    }

    public function getWebhookById(string $id): ?Webhook
    {
        try {
            return Webhook::find($id);
        } catch (Exception $e) {
        } catch (Exception $e) {
            return $this->returnResponse('error', 'Failed to find webhook: ' . $e->getMessage(), 500);
        }
    }

    public function returnResponse(string $status, string $message, int $code = 200, array $data = []): array
    {
        return [
            'status'  => $status,
            'message' => $message,
            'code'    => $code,
            'data'    => $data,
        ];
    }
}
