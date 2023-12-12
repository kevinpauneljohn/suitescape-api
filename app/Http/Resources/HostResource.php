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
        $areAllReviewsLoaded = fn () => $this->listings->every(function ($listing) {
            return $listing->relationLoaded('reviews');
        });

        $areAllReviewsCounted = fn () => $this->listings->every(function ($listing) {
            return $listing->reviews_count !== null;
        });

        $areAllLikesCounted = fn () => $this->listings->every(function ($listing) {
            return $listing->likes_count !== null;
        });

        return array_merge((new UserResource($this))->resolve(), [
            'total_likes_count' => $this->whenLoaded('listings', fn () => $this->when($areAllLikesCounted(), $this->listings->sum->likes_count)),
            'total_reviews_count' => $this->whenLoaded('listings', fn () => $this->when($areAllReviewsCounted(), $this->listings->sum->reviews_count)),
            'listings_count' => $this->whenCounted('listings'),
            'listings' => ListingResource::collection($this->whenLoaded('listings')),
            'all_reviews' => $this->whenLoaded('listings', fn () => $this->when($areAllReviewsLoaded(), ReviewResource::collection($this->listings->flatMap->reviews))),
        ]);
    }
}
