<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingNearbyPlaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nearbyPlace = $this->whenLoaded('nearbyPlace', fn () => (new NearbyPlaceResource($this->nearbyPlace))->resolve(), []);

        return array_merge($nearbyPlace, [
            'id' => $this->id, // Listing nearby place id
            $this->mergeUnless($this->relationLoaded('nearbyPlace'), ['nearby_place_id' => $this->nearby_place_id]), // Add nearby place id instead if nearby place is not loaded
        ]);
    }
}
