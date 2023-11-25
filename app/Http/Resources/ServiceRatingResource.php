<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRatingResource extends JsonResource
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
            'cleanliness' => $this->cleanliness,
            'price_affordability' => $this->price_affordability,
            'facility_service' => $this->facility_service,
            'comfortability' => $this->comfortability,
            'staff' => $this->staff,
            'location' => $this->location,
            'privacy_and_security' => $this->privacy_and_security,
            'accessibility' => $this->accessibility,
        ];
    }
}
