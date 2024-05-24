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

        return array_merge($amenity, [
            'id' => $this->id, // Room amenity id
            $this->mergeUnless($this->relationLoaded('amenity'), ['amenity_id' => $this->amenity_id]), // Add amenity id instead if amenity is not loaded
        ]);
    }
}
