<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ServiceRatingCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cleanliness' => $this->collection->avg('cleanliness'),
            'price_affordability' => $this->collection->avg('price_affordability'),
            'facility_service' => $this->collection->avg('facility_service'),
            'comfortability' => $this->collection->avg('comfortability'),
            'staff' => $this->collection->avg('staff'),
            'location' => $this->collection->avg('location'),
            'privacy_and_security' => $this->collection->avg('privacy_and_security'),
            'accessibility' => $this->collection->avg('accessibility'),
        ];
    }
}
