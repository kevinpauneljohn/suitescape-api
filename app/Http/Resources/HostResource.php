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
            'reviews' => ReviewResource::collection($this->whenLoaded('listingsReviews')),
            'listings' => ListingResource::collection($this->whenLoaded('listings')),
            'listings_count' => $this->whenCounted('listings'),
            'listings_likes_count' => $this->whenCounted('listingsLikes'),
            'listings_reviews_count' => $this->whenCounted('listingsReviews'),
            'listings_avg_rating' => $this->whenAggregated('listingsReviews', 'rating', 'avg', fn ($value) => round($value, 1)),
        ];

        return array_merge((new UserResource($this))->resolve(), $hostMetrics);
    }
}
