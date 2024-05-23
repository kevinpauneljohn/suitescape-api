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
            $this->mergeUnless($this->relationLoaded('listing'), ['listing_id' => $this->listing_id]),
            $this->mergeUnless($this->relationLoaded('coupon'), ['coupon_id' => $this->coupon_id]),
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'coupon' => new CouponResource($this->whenLoaded('coupon')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'booking_rooms' => BookingRoomResource::collection($this->whenLoaded('bookingRooms')),
            'booking_addons' => BookingAddonResource::collection($this->whenLoaded('bookingAddons')),
            'amount' => $this->amount,
            'message' => $this->message,
            'status' => $this->status,
            'date_start' => $this->date_start,
            'date_end' => $this->date_end,
            'cancellation_fee' => $this->cancellation_fee,

            $this->mergeWhen($this->cancellation_policy, fn () => [
                'cancellation_policy' => $this->cancellation_policy,
            ]),
            $this->mergeWhen($this->suitescape_cancellation_fee, fn () => [
                'suitescape_cancellation_fee' => $this->suitescape_cancellation_fee,
            ]),
            //            'created_at' => $this->created_at,
        ];
    }
}
