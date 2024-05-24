<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
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
            $this->mergeUnless($this->relationLoaded('host'), [
                'host_id' => $this->user_id,
            ]),
            'host' => new HostResource($this->whenLoaded('host')),
            'name' => $this->name,
            'location' => $this->location,
            'description' => $this->description,
            'facility_type' => $this->facility_type,
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'adult_capacity' => $this->adult_capacity,
            'child_capacity' => $this->child_capacity,
            'is_pet_friendly' => $this->is_pet_friendly,
            'parking_lot' => $this->parking_lot,
            'is_entire_place' => boolval($this->is_entire_place),
            'entire_place_price' => $this->entire_place_price ? floatval($this->entire_place_price) : null,
            'service_rating' => new ServiceRatingCollection($this->whenLoaded('serviceRatings')),
            'lowest_room_price' => $this->whenNotNull($this->whenAggregated('roomCategories', 'price', 'min', fn ($value) => floor($value))),
            'average_rating' => $this->whenNotNull($this->whenAggregated('reviews', 'rating', 'avg', fn ($value) => round($value, 1))),
            'likes_count' => $this->whenCounted('likes'),
            'saves_count' => $this->whenCounted('saves'),
            'views_count' => $this->whenCounted('views'),
            'reviews_count' => $this->whenCounted('reviews'),
            'images' => ImageResource::collection($this->whenLoaded('images', fn () => $this->images, $this->whenLoaded('publicImages'))),
            'videos' => VideoResource::collection($this->whenLoaded('videos', fn () => $this->videos, $this->whenLoaded('publicVideos'))),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'nearby_places' => ListingNearbyPlaceResource::collection($this->whenLoaded('listingNearbyPlaces')),
            'addons' => AddonResource::collection($this->whenLoaded('addons')),
            'unavailable_dates' => UnavailableDateResource::collection($this->whenLoaded('unavailableDates')),
            'booking_policies' => BookingPolicyResource::collection($this->whenLoaded('bookingPolicies')),

            $this->mergeWhen($this->relationLoaded('bookingPolicies') && $this->cancellation_policy, fn () => [
                'cancellation_policy' => $this->cancellation_policy,
            ]),

            $this->mergeWhen($user, fn () => [
                'is_liked' => $this->isLikedBy($user),
                'is_saved' => $this->isSavedBy($user),
                'is_viewed' => $this->isViewedBy($user),
            ]),
        ];
    }
}
