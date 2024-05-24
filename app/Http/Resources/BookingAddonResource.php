<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingAddonResource extends JsonResource
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
            $this->mergeUnless($this->relationLoaded('booking'), ['booking_id' => $this->booking_id]),
            $this->mergeUnless($this->relationLoaded('addon'), ['addon_id' => $this->addon_id]),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'addon' => new AddonResource($this->whenLoaded('addon')),
            'quantity' => $this->quantity,
            'price' => $this->price,
        ];
    }
}
