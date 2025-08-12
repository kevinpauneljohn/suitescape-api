<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'hook_id' => $this->hook_id,
            'type' => $this->type,
            'disabled_reason' => $this->disabled_reason,
            'events' => $this->events,
            'livemode' => $this->livemode,
            'secret_key' => $this->secret_key,
            'status' => $this->status,
            'url' => $this->url,
            'paymongo_created_at' => $this->paymongo_created_at,
            'paymongo_updated_at' => $this->paymongo_updated_at,
        ];
    }
}
