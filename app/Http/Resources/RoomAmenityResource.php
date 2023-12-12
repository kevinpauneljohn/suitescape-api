<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomAmenityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amenity = $this->whenLoaded('amenity', fn () => (new AmenityResource($this->amenity))->resolve(), []);

        $roomAmenity = [
            'id' => $this->id,
            $this->mergeUnless($this->relationLoaded('amenity'), ["amenity_id" => $this->amenity_id]),
        ];

        return array_merge($amenity, $roomAmenity);
    }
}
