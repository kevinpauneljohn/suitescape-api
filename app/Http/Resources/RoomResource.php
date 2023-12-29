<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
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
            $this->mergeUnless($this->relationLoaded('listing'), [
                'listing_id' => $this->listing_id,
            ]),
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'description' => $this->description,
            'rules' => $this->whenLoaded('roomRule'),
            'average_rating' => $this->whenNotNull($this->whenAggregated('reviews', 'rating', 'avg', fn ($value) => round($value, 1))),
            'category' => new RoomCategoryResource($this->whenLoaded('roomCategory')),
            'amenities' => RoomAmenityResource::collection($this->whenLoaded('roomAmenities')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'reviews_count' => $this->whenCounted('reviews'),
        ];
    }
}
