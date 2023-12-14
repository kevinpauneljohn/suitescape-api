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
        return [
            'id' => $this->id,
            $this->mergeUnless($this->relationLoaded('room'), ['room_id' => $this->room_id]),
            $this->mergeUnless($this->relationLoaded('user'), ['user_id' => $this->user_id]),
            $this->mergeUnless($this->relationLoaded('listing'), ['listing_id' => $this->listing_id]),
            'content' => $this->content,
            'rating' => $this->rating,
            'room' => new RoomResource($this->whenLoaded('room')),
            'user' => new UserResource($this->whenLoaded('user')),
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'created_at' => $this->created_at,
        ];
    }
}
