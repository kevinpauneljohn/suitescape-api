<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
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
            $this->mergeUnless($this->relationLoaded('cart'), [
                'cart_id' => $this->cart_id,
            ]),
            $this->mergeUnless($this->relationLoaded('room'), [
                'room_id' => $this->room_id,
            ]),
            'cart' => new CartResource($this->whenLoaded('cart')),
            'room' => new RoomResource($this->whenLoaded('room')),
        ];
    }
}
