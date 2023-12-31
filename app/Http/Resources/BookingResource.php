<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'coupon' => new CouponResource($this->whenLoaded('coupon')),
            $this->mergeUnless($this->relationLoaded('coupon'), ['coupon_id' => $this->coupon_id]),
            'booking_rooms' => BookingRoomResource::collection($this->whenLoaded('bookingRooms')),
            'amount' => $this->amount,
            'message' => $this->message,
            'status' => $this->status,
            //            'created_at' => $this->created_at,
        ];
    }
}
