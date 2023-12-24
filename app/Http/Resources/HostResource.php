<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hostMetrics = [
            'listings_count' => $this->whenCounted('listings'),
            'listings_likes_count' => $this->whenCounted('listingsLikes'),
            'listings_reviews_count' => $this->whenCounted('listingsReviews'),
            'listings' => ListingResource::collection($this->whenLoaded('listings')),
            'reviews' => ReviewResource::collection($this->whenLoaded('listingsReviews')),
        ];

        return array_merge((new UserResource($this))->resolve(), $hostMetrics);
    }
}
