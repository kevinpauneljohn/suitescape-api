<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookResource;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use App\Http\Requests\WebhookRequest;

class WebhookController extends Controller
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    public function store(WebhookRequest $request)
    {
        $attributes = $request->input('data.attributes');
        $webhook = $this->webhookService->createWebhook($attributes);
        if ($webhook['status'] === 'error') {
            return response()->json(['error' => $webhook['message']], 422);
        }

        $webhookId = $webhook['data']['hook_id'] ?? null;
        if (!$webhookId) {
            return response()->json(['error' => 'Webhook ID not found in response'], 422);
        }

        $webhook = Webhook::where('hook_id', $webhookId)->first();
        if (!$webhook) {
            return response()->json(['error' => 'Webhook not found in local database'], 404);
        }

        return new WebhookResource($webhook);
    }


    public function update(Request $request)
    {
        $id = $request->input('id');
        $attributes = $request->input('data.attributes');

        $webhook = $this->webhookService->updateWebhook($attributes, $id);
        \Log::info('Webhook update response', [
            'webhook' => $webhook,
        ]);
        if ($webhook['status'] === 'error') {
            return response()->json(['error' => $webhook['message']], $webhook['code']);
        }

        $data = $webhook['data'] ?? $webhook;
        return $data;
    }


    public function showAllData()
    {
        $webhooks = Webhook::all();
        return WebhookResource::collection($webhooks);
    }

    public function showAllFromPaymongo()
    {
        $webhooks = $this->webhookService->getAllWebhooksFromApi();

        return $webhooks;
    }

    public function showApi($id)
    {
        $getWebhook = $this->webhookService->getWebhookApiById($id);
        if (!$getWebhook) {
            return response()->json(['error' => $getWebhook['message']], 404);
        }

        $webhookId = $getWebhook['id'] ?? null;    
        if (!$webhookId) {
            return response()->json(['error' => 'Webhook not found'], 404);
        }    
        $webhook = Webhook::find($webhookId);
        if (!$webhook) {
            $getWebhook['message'] = 'Webhook not found in local database';
            return response()->json($getWebhook, 404);
        }

        return new WebhookResource($webhook);
    }

    public function destroyApi($id)
    {
        $webhook = $this->webhookService->deleteWebhookApiById($id);
        if (!$webhook) {
            return response()->json(['error' => $webhook['message']], 404);
        }

        return response()->json(['message' => 'Webhook deleted successfully']);
    }

    public function changeStatus($id, $status)
    {
        $getWebhook = $this->webhookService->changeWebhookStatus($id, $status);
        if (!$getWebhook) {
            return response()->json(['error' => 'Webhook not found'], 404);
        }

        $webhook = Webhook::where('hook_id', $id)->first();
        if (!$webhook) {
            return response()->json(['error' => 'Webhook not found in local database'], 404);
        }

        return new WebhookResource($webhook);
    }
}
