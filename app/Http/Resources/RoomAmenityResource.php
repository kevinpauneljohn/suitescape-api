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
        return [
            'id' => $this->id,
            'amenity_id' => $this->whenLoaded('amenity', fn () => $this->amenity->id),
            'name' => $this->whenLoaded('amenity', fn () => $this->amenity->name),
        ];
    }
}
