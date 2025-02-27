<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            $this->mergeUnless($this->relationLoaded('user'), ['user_id' => $this->user_id]),
            $this->mergeUnless($this->relationLoaded('booking'), ['booking_id' => $this->booking_id]),
            'user' => new UserResource($this->whenLoaded('user')),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'payment_status' => $this->payment_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
