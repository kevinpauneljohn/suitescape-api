<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GcashAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone_number' => $this->phone_number,
            'account_name' => $this->account_name,
            'payout_method' => $this->whenLoaded('payoutMethod'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
