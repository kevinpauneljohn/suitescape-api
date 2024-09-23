<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingRoomResource extends JsonResource
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
            $this->mergeUnless($this->relationLoaded('room'), ['room_id' => $this->room_id]),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'room' => new RoomResource($this->whenLoaded('room')),
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->price,
        ];
    }
}
