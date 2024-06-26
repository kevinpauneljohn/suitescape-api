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
            'rule' => new RoomRuleResource($this->whenLoaded('roomRule')),
            'category' => new RoomCategoryResource($this->whenLoaded('roomCategory')),
            'amenities' => RoomAmenityResource::collection($this->whenLoaded('roomAmenities')),
            'special_rates' => SpecialRateResource::collection($this->whenLoaded('specialRates')),
            'unavailable_dates' => UnavailableDateResource::collection($this->whenLoaded('unavailableDates')),
        ];
    }
}
