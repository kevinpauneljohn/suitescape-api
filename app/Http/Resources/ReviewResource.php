<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $guestReview = $this->booking?->guestReview ?? null;

        return [
            'id' => $this->id,
            $this->mergeUnless($this->relationLoaded('user'), ['user_id' => $this->user_id]),
            $this->mergeUnless($this->relationLoaded('listing'), ['listing_id' => $this->listing_id]),
            'booking_id' => $this->booking_id,
            'content' => $this->content,
            'rating' => $this->rating,
            'reviewed_at' => $this->reviewed_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'listing' => new ListingResource($this->whenLoaded('listing')),
            // Airbnb-style: host's rating of the guest is public so other hosts
            // can make informed decisions when accepting future bookings.
            'guest_review' => $guestReview ? [
                'id'      => $guestReview->id,
                'rating'  => $guestReview->rating,
                'content' => $guestReview->content,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
