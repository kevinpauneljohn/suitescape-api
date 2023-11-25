<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    protected string $cancellationPolicy;

    public function __construct($resource, $cancellationPolicy = '')
    {
        parent::__construct($resource);
        $this->cancellationPolicy = $cancellationPolicy;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'description' => $this->description,
            'host' => new UserResource($this->whenLoaded('user')),
            //            'service_rating' => $this->whenLoaded('serviceRatings', fn () => [
            //                'cleanliness' => $this->serviceRatings->avg('cleanliness'),
            //                'price_affordability' => $this->serviceRatings->avg('price_affordability'),
            //                'facility_service' => $this->serviceRatings->avg('facility_service'),
            //                'comfortability' => $this->serviceRatings->avg('comfortability'),
            //                'staff' => $this->serviceRatings->avg('staff'),
            //                'location' => $this->serviceRatings->avg('location'),
            //                'privacy_and_security' => $this->serviceRatings->avg('privacy_and_security'),
            //                'accessibility' => $this->serviceRatings->avg('accessibility'),
            //            ]),
            'service_rating' => new ServiceRatingCollection($this->whenLoaded('serviceRatings')),
            'lowest_room_price' => $this->whenNotNull($this->whenAggregated('roomCategories', 'price', 'min', fn ($value) => round($value))),
            'average_rating' => $this->whenNotNull($this->whenAggregated('reviews', 'rating', 'avg', fn ($value) => round($value, 1))),
            'likes_count' => $this->whenCounted('likes'),
            'saves_count' => $this->whenCounted('saves'),
            'views_count' => $this->whenCounted('views'),
            'reviews_count' => $this->whenCounted('reviews'),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'videos' => VideoResource::collection($this->whenLoaded('videos')),
            'nearby_places' => NearbyPlaceResource::collection($this->whenLoaded('nearbyPlaces')),
            'booking_policies' => BookingPolicyResource::collection($this->whenLoaded('bookingPolicies')),
            'cancellation_policy' => $this->whenLoaded('bookingPolicies', $this->cancellationPolicy),

            $this->mergeWhen($user, $user ? [
                'is_liked' => $this->isLikedBy($user),
                'is_saved' => $this->isSavedBy($user),
                'is_viewed' => $this->isViewedBy($user),
            ] : []),
        ];
    }
}
