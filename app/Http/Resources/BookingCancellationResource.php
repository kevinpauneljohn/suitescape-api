<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingCancellationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'user_id' => $this->user_id,
            'payment_id' => $this->payment_id,
            'refund_id' => $this->refund_id,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'status' => $this->status,
            'currency' => $this->currency,
            'refunded_at' => $this->refunded_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
