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
            $this->mergeUnless($this->relationLoaded('user'), ['user_id' => $this->user_id]),
            $this->mergeUnless($this->relationLoaded('listing'), ['listing_id' => $this->listing_id]),
            $this->mergeUnless($this->relationLoaded('coupon'), ['coupon_id' => $this->coupon_id]),
            'user' => new UserResource($this->whenLoaded('user')),
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'coupon' => new CouponResource($this->whenLoaded('coupon')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'booking_rooms' => BookingRoomResource::collection($this->whenLoaded('bookingRooms')),
            'booking_addons' => BookingAddonResource::collection($this->whenLoaded('bookingAddons')),
            'amount' => floatval($this->amount),
            'base_amount' => floatval($this->base_amount),
            'message' => $this->message,
            'status' => $this->status,
            'date_start' => $this->date_start,
            'date_end' => $this->date_end,

            $this->mergeWhen(gettype($this->is_expired) === 'boolean', fn () => [
                'is_expired' => boolval($this->is_expired),
            ]),
            $this->mergeWhen($this->cancellation_reason, fn () => [
                'cancellation_reason' => $this->cancellation_reason,
            ]),
            $this->mergeWhen($this->cancellation_policy, fn () => [
                'cancellation_policy' => $this->cancellation_policy,
            ]),
            $this->mergeWhen($this->cancellation_fee, fn () => [
                'cancellation_fee' => floatval($this->cancellation_fee),
            ]),
            $this->mergeWhen($this->suitescape_cancellation_fee, fn () => [
                'suitescape_cancellation_fee' => floatval($this->suitescape_cancellation_fee),
            ]),
            //            'created_at' => $this->created_at,
        ];
    }
}
